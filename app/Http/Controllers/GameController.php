<?php

namespace App\Http\Controllers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $transactions = DB::table('chance_transactions')->where(['activity_id' => $activity->id, 'user_id' => $request->user()->id])->latest()->limit(20)->get();
        $winnings = DB::table('winning_records')->where(['activity_id' => $activity->id, 'user_id' => $request->user()->id])->latest()->limit(20)->get();
        $invites = DB::table('invitation_rewards')->where(['activity_id' => $activity->id, 'inviter_id' => $request->user()->id])->count();
        $skinIcon = DB::table('skin_definitions')->where('id', $request->user()->equipped_skin_id)->value('icon') ?? '🚗';
        $unreadMessages = DB::table('activity_messages')->where('user_id', $request->user()->id)->whereNull('read_at')->count();

        return view('game.index', compact('activity', 'state', 'cells', 'leaderboard', 'checkedIn', 'lastCheckin', 'transactions', 'winnings', 'invites', 'skinIcon', 'unreadMessages'));
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
            return response()->json($existing);
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

                    return $this->saveMove($data['request_id'], $activity->id, $request->user()->id, 'unfreeze', null, $state->completed_laps, $state->current_position, $state->completed_laps, $state->current_position, 'unfreeze', '解冻成功，下次可以继续前进');
                }
                $dice = random_int(1, 6);
                if ($this->consumeEffect($request->user()->id, $activity->id, 'reroll')) {
                    $dice = max($dice, random_int(1, 6));
                }
                if ($this->consumeEffect($request->user()->id, $activity->id, 'double')) {
                    $dice *= 2;
                }
                $raw = $state->current_position + $dice;
                $lap = $state->completed_laps + intdiv($raw, 36);
                $position = $raw % 36;
                $cell = DB::table('board_cells')->where(['activity_id' => $activity->id, 'position' => $position])->first();
                $text = $cell->label;
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
                $updates = ['completed_laps' => $lap, 'current_position' => $position, 'progress_reached_at' => now(), 'updated_at' => now()];
                if ($cell->type === 'freeze') {
                    if ($this->consumeEffect($request->user()->id, $activity->id, 'lucky')) {
                        $text = '幸运卡生效，免疫本次冰冻';
                    } else {
                        $updates['is_frozen'] = true;
                    }
                }
                DB::table('activity_users')->where('id', $state->id)->update($updates);
                $move = $this->saveMove($data['request_id'], $activity->id, $request->user()->id, 'move', $dice, $state->completed_laps, $state->current_position, $lap, $position, $cell->type, $text);
                $this->awardCell($activity->id, $request->user(), $cell, $move->id);
                $this->syncUnlocks($activity->id, $request->user()->id, $lap);

                return $move;
            });

            return response()->json($result);
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

    private function saveMove(string $id, int $activityId, int $userId, string $action, ?int $dice, int $fromLap, int $fromPos, int $toLap, int $toPos, string $type, string $text): object
    {
        $pk = DB::table('board_moves')->insertGetId(['request_id' => $id, 'activity_id' => $activityId, 'user_id' => $userId, 'action_type' => $action, 'dice_value' => $dice, 'from_lap' => $fromLap, 'from_position' => $fromPos, 'to_lap' => $toLap, 'to_position' => $toPos, 'cell_type' => $type, 'result_text' => $text, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('board_moves')->find($pk);
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
}
