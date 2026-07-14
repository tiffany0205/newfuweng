<?php

namespace App\Http\Controllers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(Request $request): View
    {
        $activity = $this->activity();
        $state = $this->state($activity->id, $request->user()->id);
        $cells = DB::table('board_cells')->where('activity_id', $activity->id)->orderBy('position')->get();
        $leaderboard = $this->leaderboard($activity->id);
        $today = now($activity->timezone)->toDateString();
        $checkedIn = DB::table('checkins')->where(['activity_id' => $activity->id, 'user_id' => $request->user()->id, 'checkin_date' => $today])->exists();
        $lastCheckin = DB::table('checkins')->where(['activity_id' => $activity->id, 'user_id' => $request->user()->id])->latest('checkin_date')->first();
        $transactionPage = $this->recordPage('chance_transactions', $activity->id, $request->user()->id);
        $winningPage = $this->recordPage('winning_records', $activity->id, $request->user()->id);
        $transactions = $transactionPage['data'];
        $winnings = $winningPage['data'];
        $transactionCursor = $transactionPage['next_cursor'];
        $winningCursor = $winningPage['next_cursor'];
        $hasMoreTransactions = $transactionPage['has_more'];
        $hasMoreWinnings = $winningPage['has_more'];
        $invites = DB::table('invitation_rewards')->where(['activity_id' => $activity->id, 'inviter_id' => $request->user()->id])->count();
        $qualifiedInvites = DB::table('invitation_rewards')->where(['activity_id' => $activity->id, 'inviter_id' => $request->user()->id, 'recharge_awarded' => true])->count();
        $skinIcon = DB::table('skin_definitions')->where('id', $request->user()->equipped_skin_id)->value('icon') ?? '🚗';
        $unreadMessages = DB::table('activity_messages')->where('user_id', $request->user()->id)->whereNull('read_at')->count();
        $unlockedLandmarks = DB::table('user_landmarks')->where(['activity_id' => $activity->id, 'user_id' => $request->user()->id])->pluck('visit_count', 'board_cell_id');

        return view('game.index', compact('activity', 'state', 'cells', 'leaderboard', 'checkedIn', 'lastCheckin', 'transactions', 'winnings', 'transactionCursor', 'winningCursor', 'hasMoreTransactions', 'hasMoreWinnings', 'invites', 'qualifiedInvites', 'skinIcon', 'unreadMessages', 'unlockedLandmarks'));
    }

    public function chanceRecords(Request $request): JsonResponse
    {
        $data = $request->validate(['cursor' => ['required', 'integer', 'min:1']]);
        $activity = $this->activity();
        $page = $this->recordPage('chance_transactions', $activity->id, $request->user()->id, (int) $data['cursor']);

        return response()->json([
            'data' => $page['data']->map(fn ($row) => [
                'id' => (int) $row->id,
                'created_at' => $row->created_at,
                'remark' => $row->remark ?? '—',
                'amount' => (int) $row->amount,
                'balance_after' => (int) $row->balance_after,
            ])->values(),
            'next_cursor' => $page['next_cursor'],
            'has_more' => $page['has_more'],
        ]);
    }

    public function winningRecords(Request $request): JsonResponse
    {
        $data = $request->validate(['cursor' => ['required', 'integer', 'min:1']]);
        $activity = $this->activity();
        $page = $this->recordPage('winning_records', $activity->id, $request->user()->id, (int) $data['cursor']);

        return response()->json([
            'data' => $page['data']->map(fn ($row) => [
                'id' => (int) $row->id,
                'created_at' => $row->created_at,
                'prize_name' => $row->prize_name,
                'status_label' => $row->status === 'issued' ? '已发放' : '待发放',
            ])->values(),
            'next_cursor' => $page['next_cursor'],
            'has_more' => $page['has_more'],
        ]);
    }

    public function taskRewardRecords(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['invite', 'friend_recharge'])],
            'cursor' => ['nullable', 'integer', 'min:1'],
        ]);
        $activity = $this->activity();
        $cursor = isset($data['cursor']) ? (int) $data['cursor'] : null;
        $page = $data['type'] === 'invite'
            ? $this->inviteRewardPage($activity->id, $request->user()->id, $cursor)
            : $this->friendRechargeRewardPage($activity->id, $request->user()->id, $cursor);

        return response()->json($page);
    }

    public function checkin(Request $request): RedirectResponse
    {
        $activity = $this->activity();
        $today = now($activity->timezone)->toDateString();
        $yesterday = now($activity->timezone)->subDay()->toDateString();
        try {
            DB::transaction(function () use ($activity, $request, $today, $yesterday) {
                $last = DB::table('checkins')->where(['activity_id' => $activity->id, 'user_id' => $request->user()->id])->latest('checkin_date')->first();
                $streak = $last && $last->checkin_date === $yesterday ? $last->streak_day + 1 : 1;
                $amount = $streak % 7 === 0 ? 10 : 5;
                DB::table('checkins')->insert(['activity_id' => $activity->id, 'user_id' => $request->user()->id, 'checkin_date' => $today, 'streak_day' => $streak, 'chance_awarded' => $amount, 'created_at' => now(), 'updated_at' => now()]);
                $this->changeChance($activity->id, $request->user()->id, $amount, 'checkin', "checkin-{$request->user()->id}-{$today}", "连续签到第 {$streak} 天");
            });
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', '今天已经签到过了');
        }

        return back()->with('success', '签到成功，跳棋机会已到账');
    }

    public function move(Request $request): JsonResponse
    {
        $data = $request->validate(['request_id' => ['required', 'uuid']]);
        $activity = $this->activity();
        $existing = DB::table('board_moves')->where('request_id', $data['request_id'])->first();
        if ($existing) {
            return $this->moveResponse($existing);
        }

        try {
            if ($activity->status !== 'active' || now()->lt($activity->starts_at) || now()->gt($activity->ends_at)) {
                throw new \RuntimeException('活动当前不在可走棋时间内');
            }
            $result = DB::transaction(function () use ($activity, $request, $data) {
                $state = DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $request->user()->id])->first();
                if (! $state || $state->chance_balance < 1) {
                    throw new \RuntimeException('跳棋机会不足，请先完成任务');
                }
                $this->changeChance($activity->id, $request->user()->id, -1, $state->is_frozen ? 'unfreeze' : 'move', 'move-'.$data['request_id'], $state->is_frozen ? '冰冻解冻' : '跳棋消耗');
                if ($state->is_frozen) {
                    DB::table('activity_users')->where('id', $state->id)->update(['is_frozen' => false, 'updated_at' => now()]);

                    $finalLabel = DB::table('board_cells')->where(['activity_id' => $activity->id, 'position' => $state->current_position])->value('label') ?? '当前位置';

                    $move = $this->saveMove($data['request_id'], $activity->id, $request->user()->id, 'unfreeze', null, $state->completed_laps, $state->current_position, $state->completed_laps, $state->current_position, 'unfreeze', '解冻成功，下次可以继续前进', 'normal', $finalLabel, false);
                    $this->syncMoveTransaction($move);

                    return $move;
                }
                $dice = random_int(1, 6);
                if ($this->consumeEffect($request->user()->id, $activity->id, 'reroll')) {
                    $dice = max($dice, random_int(1, 6));
                }
                if ($this->consumeEffect($request->user()->id, $activity->id, 'double')) {
                    $dice *= 2;
                }
                if ($this->consumeEffect($request->user()->id, $activity->id, 'high_roll')) {
                    $dice = max(4, $dice);
                }
                $raw = $state->current_position + $dice;
                $lap = $state->completed_laps + intdiv($raw, 36);
                $position = $raw % 36;
                $cell = DB::table('board_cells')->where(['activity_id' => $activity->id, 'position' => $position])->first();
                $text = $cell->type === 'normal' && $cell->category === 'safe' ? $cell->label.'：安全抵达，本格没有特殊事件' : $cell->label;
                if ($cell->type === 'forward') {
                    $raw = $position + $cell->value;
                    $lap += intdiv($raw, 36);
                    $position = $raw % 36;
                    $text .= "，额外前进 {$cell->value} 格";
                }
                if ($cell->type === 'backward') {
                    $position = max(0, $position - $cell->value);
                    $text .= "，后退 {$cell->value} 格";
                }
                if ($cell->type === 'bomb') {
                    if ($this->consumeEffect($request->user()->id, $activity->id, 'shield') || $this->consumeEffect($request->user()->id, $activity->id, 'lucky')) {
                        $text = '防护道具生效，成功抵挡炸弹';
                    } else {
                        $position = 0;
                        $text = '踩中炸弹，回到本圈起点';
                    }
                }
                $finalCell = $position === $cell->position
                    ? $cell
                    : DB::table('board_cells')->where(['activity_id' => $activity->id, 'position' => $position])->first();
                $updates = ['completed_laps' => $lap, 'current_position' => $position, 'progress_reached_at' => now(), 'updated_at' => now()];
                if ($cell->type === 'freeze') {
                    if ($this->consumeEffect($request->user()->id, $activity->id, 'freeze_guard') || $this->consumeEffect($request->user()->id, $activity->id, 'lucky')) {
                        $text = '防护效果生效，免疫本次冰冻';
                    } else {
                        $updates['is_frozen'] = true;
                    }
                }
                if ($finalCell->id !== $cell->id && in_array($cell->type, ['forward', 'backward'], true)) {
                    $text .= "，最终抵达 {$finalCell->label}";
                }
                $landmarkCell = $cell->category === 'landmark'
                    ? $cell
                    : ($finalCell->category === 'landmark' ? $finalCell : null);
                $landmarkUnlocked = false;
                if ($landmarkCell) {
                    $landmarkResult = $this->visitLandmark($activity->id, $request->user()->id, $landmarkCell, $data['request_id']);
                    $text .= $landmarkResult['text'];
                    $landmarkUnlocked = $landmarkResult['unlocked'];
                }
                $feedbackType = $landmarkCell ? 'landmark' : match ($cell->category) {
                    'boost' => 'boost',
                    'reward' => 'reward',
                    'risk' => 'risk',
                    default => 'normal',
                };
                DB::table('activity_users')->where('id', $state->id)->update($updates);
                $move = $this->saveMove($data['request_id'], $activity->id, $request->user()->id, 'move', $dice, $state->completed_laps, $state->current_position, $lap, $position, $cell->type, $text, $feedbackType, $finalCell->label, $landmarkUnlocked);
                $this->syncMoveTransaction($move);
                $this->awardCell($activity->id, $request->user(), $cell, $move->id);
                $this->syncUnlocks($activity->id, $request->user()->id, $lap);

                return $move;
            });

            return $this->moveResponse($result);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function awardCell(int $activityId, $user, object $cell, int $moveId): void
    {
        if ($cell->type === 'chance') {
            $this->changeChance($activityId, $user->id, $cell->value, 'cell_reward', "cell-{$moveId}", $cell->label);
        }
        if ($cell->type === 'battery') {
            DB::table('users')->where('id', $user->id)->increment('battery', $cell->value);
            $this->winning($activityId, $user->id, $moveId, $cell->label, 'issued');
        }
        if ($cell->type === 'vip') {
            DB::table('users')->where('id', $user->id)->increment('vip_level', 1);
            $this->winning($activityId, $user->id, $moveId, $cell->label, 'issued');
        }
        if ($cell->type === 'prize') {
            $this->winning($activityId, $user->id, $moveId, $cell->label, 'pending');
        }
    }

    private function winning(int $activityId, int $userId, int $moveId, string $name, string $status): void
    {
        DB::table('winning_records')->insert(['activity_id' => $activityId, 'user_id' => $userId, 'board_move_id' => $moveId, 'prize_name' => $name, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('activity_messages')->insert(['user_id' => $userId, 'type' => 'winning', 'title' => '恭喜获得 '.$name, 'content' => $status === 'issued' ? '奖励已自动到账。' : '请前往幸运中心提交领奖资料。', 'action_url' => '/activity/center#prizes', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function consumeEffect(int $userId, int $activityId, string $effect): bool
    {
        $active = DB::table('user_active_effects')->where(['user_id' => $userId, 'activity_id' => $activityId, 'effect' => $effect])->where('remaining_uses', '>', 0)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->first();
        if (! $active) {
            return false;
        }
        DB::table('user_active_effects')->where('id', $active->id)->decrement('remaining_uses');

        return true;
    }

    private function visitLandmark(int $activityId, int $userId, object $cell, string $requestId): array
    {
        $record = DB::table('user_landmarks')->where(['activity_id' => $activityId, 'user_id' => $userId, 'board_cell_id' => $cell->id])->first();
        $first = ! $record;
        if ($first) {
            DB::table('user_landmarks')->insert(['activity_id' => $activityId, 'user_id' => $userId, 'board_cell_id' => $cell->id, 'visit_count' => 1, 'first_unlocked_at' => now(), 'last_visited_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('user_landmarks')->where('id', $record->id)->update(['visit_count' => $record->visit_count + 1, 'last_visited_at' => now(), 'updated_at' => now()]);
            DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $userId])->increment('lucky_points');
        }
        $effectText = '';
        if ($cell->effect_code === 'free_roll') {
            $this->changeChance($activityId, $userId, 1, 'landmark', 'landmark-'.$requestId, $cell->label.'免费通行');
            $effectText = '，返还 1 次跳棋机会';
        } elseif (in_array($cell->effect_code, ['freeze_guard', 'reroll', 'high_roll'], true)) {
            DB::table('user_active_effects')->insert(['user_id' => $userId, 'activity_id' => $activityId, 'effect' => $cell->effect_code, 'remaining_uses' => 1, 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
            $effectText = match ($cell->effect_code) {
                'freeze_guard' => '，获得一次冰冻免疫', 'reroll' => '，下一次自动重掷并取较大点数', 'high_roll' => '，下一次骰子最低 4 点'
            };
        } elseif (str_starts_with((string) $cell->effect_code, 'lucky_')) {
            $points = (int) str_replace('lucky_', '', $cell->effect_code);
            DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $userId])->increment('lucky_points', $points);
            $effectText = "，获得 {$points} 点幸运值";
        } elseif ($cell->effect_code === 'rainbow') {
            $roll = random_int(1, 3);
            if ($roll === 1) {
                $this->changeChance($activityId, $userId, 1, 'landmark', 'landmark-'.$requestId, '彩虹惊喜');
                $effectText = '，彩虹惊喜：机会 +1';
            } elseif ($roll === 2) {
                DB::table('users')->where('id', $userId)->increment('battery');
                $effectText = '，彩虹惊喜：电池 +1';
            } else {
                DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $userId])->increment('lucky_points', 2);
                $effectText = '，彩虹惊喜：幸运值 +2';
            }
        } elseif ($cell->effect_code === 'shield' && $first) {
            $shield = DB::table('item_definitions')->where('code', 'shield')->first();
            DB::table('user_items')->insertOrIgnore(['user_id' => $userId, 'item_definition_id' => $shield->id, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('user_items')->where(['user_id' => $userId, 'item_definition_id' => $shield->id])->increment('quantity');
            $effectText = '，首次解锁额外获得防护盾 ×1';
        }
        $count = DB::table('user_landmarks')->where(['activity_id' => $activityId, 'user_id' => $userId])->count();
        $total = DB::table('board_cells')->where(['activity_id' => $activityId, 'category' => 'landmark'])->count();

        return [
            'text' => ($first ? "，新地标印章已解锁（{$count}/{$total}）" : '，重复印章转化为幸运值 +1').$effectText,
            'unlocked' => $first,
        ];
    }

    private function syncUnlocks(int $activityId, int $userId, int $laps): void
    {
        $skins = DB::table('skin_definitions')->where('unlock_type', 'laps')->where('unlock_value', '<=', $laps)->get();
        foreach ($skins as $skin) {
            DB::table('user_skins')->insertOrIgnore(['user_id' => $userId, 'skin_definition_id' => $skin->id, 'unlocked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $achievements = DB::table('achievement_definitions')->where('metric', 'laps')->where('target', '<=', $laps)->get();
        foreach ($achievements as $achievement) {
            DB::table('user_achievements')->insertOrIgnore(['user_id' => $userId, 'achievement_definition_id' => $achievement->id, 'unlocked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function saveMove(string $id, int $activityId, int $userId, string $action, ?int $dice, int $fromLap, int $fromPos, int $toLap, int $toPos, string $type, string $text, string $feedbackType, string $finalCellLabel, bool $landmarkUnlocked): object
    {
        $pk = DB::table('board_moves')->insertGetId(['request_id' => $id, 'activity_id' => $activityId, 'user_id' => $userId, 'action_type' => $action, 'dice_value' => $dice, 'from_lap' => $fromLap, 'from_position' => $fromPos, 'to_lap' => $toLap, 'to_position' => $toPos, 'cell_type' => $type, 'result_text' => $text, 'feedback_type' => $feedbackType, 'final_cell_label' => $finalCellLabel, 'landmark_unlocked' => $landmarkUnlocked, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('board_moves')->find($pk);
    }

    private function moveResponse(object $move): JsonResponse
    {
        $move->landmark_unlocked = (bool) $move->landmark_unlocked;
        $move->display_position = (int) $move->to_position + 1;
        $move->landmark_count = DB::table('user_landmarks')->where(['activity_id' => $move->activity_id, 'user_id' => $move->user_id])->count();
        $move->landmark_total = DB::table('board_cells')->where(['activity_id' => $move->activity_id, 'category' => 'landmark'])->count();
        $move->lucky_points = (int) DB::table('activity_users')->where(['activity_id' => $move->activity_id, 'user_id' => $move->user_id])->value('lucky_points');
        $transaction = DB::table('chance_transactions')->where([
            'activity_id' => $move->activity_id,
            'user_id' => $move->user_id,
            'business_key' => 'move-'.$move->request_id,
        ])->firstOrFail();
        $move->chance_transaction = [
            'id' => (int) $transaction->id,
            'created_at' => $transaction->created_at,
            'remark' => $transaction->remark,
            'amount' => (int) $transaction->amount,
            'balance_after' => (int) $transaction->balance_after,
        ];

        return response()->json($move);
    }

    private function syncMoveTransaction(object $move): void
    {
        $position = (int) $move->to_position + 1;
        $label = $move->final_cell_label ?: '当前位置';
        $remark = $move->action_type === 'unfreeze'
            ? "解冻成功 · 停留第 {$position} 格 {$label}"
            : "掷出 {$move->dice_value} 点 · 到达第 {$position} 格 {$label}";

        $updated = DB::table('chance_transactions')->where([
            'activity_id' => $move->activity_id,
            'user_id' => $move->user_id,
            'business_key' => 'move-'.$move->request_id,
        ])->update(['remark' => $remark, 'updated_at' => now()]);
        if ($updated !== 1) {
            throw new \RuntimeException('走棋机会流水更新失败');
        }
    }

    private function changeChance(int $activityId, int $userId, int $amount, string $type, string $key, string $remark): void
    {
        DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $userId])->increment('chance_balance', $amount);
        $balance = DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $userId])->value('chance_balance');
        DB::table('chance_transactions')->insert(['activity_id' => $activityId, 'user_id' => $userId, 'type' => $type, 'amount' => $amount, 'balance_after' => $balance, 'business_key' => $key, 'remark' => $remark, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function activity(): object
    {
        return DB::table('activities')->where('enabled', true)->firstOrFail();
    }

    private function state(int $activityId, int $userId): object
    {
        return DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $userId])->firstOrFail();
    }

    private function leaderboard(int $activityId)
    {
        return DB::table('activity_users')->join('users', 'users.id', '=', 'activity_users.user_id')->where('activity_id', $activityId)->where('ranking_status', 'eligible')->orderByDesc('completed_laps')->orderByDesc('current_position')->orderBy('progress_reached_at')->select('users.name', 'users.id as user_id', 'completed_laps', 'current_position')->limit(20)->get();
    }

    private function recordPage(string $table, int $activityId, int $userId, ?int $cursor = null): array
    {
        $query = DB::table($table)
            ->where(['activity_id' => $activityId, 'user_id' => $userId])
            ->orderByDesc('id');

        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        $rows = $query->limit(11)->get();
        $hasMore = $rows->count() > 10;
        $data = $rows->take(10)->values();

        return [
            'data' => $data,
            'next_cursor' => $hasMore ? (int) $data->last()->id : null,
            'has_more' => $hasMore,
        ];
    }

    private function inviteRewardPage(int $activityId, int $userId, ?int $cursor): array
    {
        $query = DB::table('invitation_rewards')
            ->join('users', 'users.id', '=', 'invitation_rewards.invitee_id')
            ->where([
                'invitation_rewards.activity_id' => $activityId,
                'invitation_rewards.inviter_id' => $userId,
                'invitation_rewards.register_awarded' => true,
            ])
            ->orderByDesc('invitation_rewards.id')
            ->select('invitation_rewards.id', 'invitation_rewards.invitee_id', 'invitation_rewards.created_at', 'users.name');

        if ($cursor !== null) {
            $query->where('invitation_rewards.id', '<', $cursor);
        }

        $rows = $query->limit(11)->get();
        $data = $rows->take(10)->values();
        $amounts = DB::table('chance_transactions')
            ->where(['activity_id' => $activityId, 'user_id' => $userId, 'type' => 'invite_register'])
            ->whereIn('business_key', $data->map(fn ($row) => 'invite-register-'.$row->invitee_id))
            ->pluck('amount', 'business_key');

        return $this->taskRewardPageResponse($data->map(fn ($row) => [
            'id' => (int) $row->id,
            'friend_name' => $this->maskFriendName($row->name),
            'occurred_at' => $row->created_at,
            'chance_awarded' => (int) ($amounts['invite-register-'.$row->invitee_id] ?? 5),
        ]), $rows->count() > 10);
    }

    private function friendRechargeRewardPage(int $activityId, int $userId, ?int $cursor): array
    {
        $query = DB::table('chance_transactions')
            ->where(['activity_id' => $activityId, 'user_id' => $userId, 'type' => 'friend_recharge'])
            ->orderByDesc('id');

        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        $transactions = $query->limit(11)->get();
        $inviteeIds = $transactions->map(fn ($row) => (int) str_replace('friend-recharge-', '', $row->business_key))->unique()->values();
        $friends = DB::table('invitation_rewards')
            ->join('users', 'users.id', '=', 'invitation_rewards.invitee_id')
            ->where(['invitation_rewards.activity_id' => $activityId, 'invitation_rewards.inviter_id' => $userId, 'invitation_rewards.recharge_awarded' => true])
            ->whereIn('invitation_rewards.invitee_id', $inviteeIds)
            ->pluck('users.name', 'invitation_rewards.invitee_id');
        $data = $transactions->filter(function ($row) use ($friends) {
            $inviteeId = (int) str_replace('friend-recharge-', '', $row->business_key);

            return $friends->has($inviteeId);
        })->take(10)->values()->map(function ($row) use ($friends) {
            $inviteeId = (int) str_replace('friend-recharge-', '', $row->business_key);

            return [
                'id' => (int) $row->id,
                'friend_name' => $this->maskFriendName($friends[$inviteeId]),
                'occurred_at' => $row->created_at,
                'chance_awarded' => (int) $row->amount,
            ];
        });

        return $this->taskRewardPageResponse($data, $transactions->count() > 10);
    }

    private function taskRewardPageResponse($data, bool $hasMore): array
    {
        return [
            'data' => $data->values(),
            'next_cursor' => $hasMore && $data->isNotEmpty() ? (int) $data->last()['id'] : null,
            'has_more' => $hasMore,
        ];
    }

    private function maskFriendName(string $name): string
    {
        return mb_substr($name, 0, 1).'***';
    }
}
