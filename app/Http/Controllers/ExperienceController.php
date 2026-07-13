<?php

namespace App\Http\Controllers;

use App\Services\RewardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExperienceController extends Controller
{
    public function __construct(private RewardService $rewards) {}

    public function center(Request $request): View
    {
        $activity = $this->activity();
        $uid = $request->user()->id;
        $state = DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $uid])->firstOrFail();
        $this->syncCollections($activity, $uid, $state);
        $tasks = DB::table('task_definitions')->where(['activity_id' => $activity->id, 'enabled' => true])->orderBy('sort_order')->get()->map(function ($task) use ($activity, $uid) {
            $task->progress_value = $this->metric($task->metric, $activity, $uid, $task->period);
            $task->period_key = $task->period === 'weekly' ? now($activity->timezone)->format('o-W') : now($activity->timezone)->toDateString();
            $task->claimed = DB::table('user_task_progress')->where(['task_definition_id' => $task->id, 'user_id' => $uid, 'period_key' => $task->period_key])->whereNotNull('claimed_at')->exists();

            return $task;
        });
        $milestones = DB::table('milestone_definitions')->where(['activity_id' => $activity->id, 'enabled' => true])->orderBy('required_laps')->get();
        $claimedMilestones = DB::table('user_milestones')->where('user_id', $uid)->pluck('milestone_definition_id')->all();
        $items = DB::table('item_definitions')->leftJoin('user_items', fn ($j) => $j->on('item_definitions.id', '=', 'user_items.item_definition_id')->where('user_items.user_id', $uid))->select('item_definitions.*', DB::raw('COALESCE(user_items.quantity,0) quantity'))->get();
        $achievements = DB::table('achievement_definitions')->leftJoin('user_achievements', fn ($j) => $j->on('achievement_definitions.id', '=', 'user_achievements.achievement_definition_id')->where('user_achievements.user_id', $uid))->select('achievement_definitions.*', 'user_achievements.unlocked_at', 'user_achievements.claimed_at')->get();
        $skins = DB::table('skin_definitions')->leftJoin('user_skins', fn ($j) => $j->on('skin_definitions.id', '=', 'user_skins.skin_definition_id')->where('user_skins.user_id', $uid))->select('skin_definitions.*', 'user_skins.unlocked_at')->get();
        $invites = DB::table('invitation_rewards')->join('users', 'users.id', '=', 'invitation_rewards.invitee_id')->leftJoin('activity_users', fn ($j) => $j->on('activity_users.user_id', '=', 'users.id')->where('activity_users.activity_id', $activity->id))->where('inviter_id', $uid)->select('users.name', 'users.created_at', 'activity_users.recharge_cents', 'invitation_rewards.register_awarded', 'invitation_rewards.recharge_awarded')->get();
        $winners = DB::table('winning_records')->join('users', 'users.id', '=', 'winning_records.user_id')->select('winning_records.*', 'users.name')->latest('winning_records.id')->limit(20)->get();
        $claims = DB::table('winning_records')->leftJoin('prize_claims', 'winning_records.id', '=', 'prize_claims.winning_record_id')->where('winning_records.user_id', $uid)->select('winning_records.*', 'prize_claims.status as claim_status')->latest('winning_records.id')->get();
        $messages = DB::table('activity_messages')->where('user_id', $uid)->latest()->limit(30)->get();
        $rankings = $this->rankings($activity->id);
        $checkins = DB::table('checkins')->where(['activity_id' => $activity->id, 'user_id' => $uid])->orderByDesc('checkin_date')->limit(14)->get();
        $prizePool = DB::table('board_cells')->where('activity_id', $activity->id)->whereIn('type', ['prize', 'vip', 'battery', 'chance'])->get();
        $season = $state->completed_laps < 6 ? ['新手旅程', 6] : ($state->completed_laps < 16 ? ['财富城市', 16] : ($state->completed_laps < 31 ? ['黄金海岸', 31] : ['冠军之路', 50]));

        return view('experience.center', compact('activity', 'state', 'tasks', 'milestones', 'claimedMilestones', 'items', 'achievements', 'skins', 'invites', 'winners', 'claims', 'messages', 'rankings', 'season', 'checkins', 'prizePool'));
    }

    public function claimTask(Request $request, int $task): RedirectResponse
    {
        $activity = $this->activity();
        $definition = DB::table('task_definitions')->where(['id' => $task, 'activity_id' => $activity->id, 'enabled' => true])->firstOrFail();
        $uid = $request->user()->id;
        abort_if($this->metric($definition->metric, $activity, $uid, $definition->period) < $definition->target, 422, '任务尚未完成');
        $key = $definition->period === 'weekly' ? now($activity->timezone)->format('o-W') : now($activity->timezone)->toDateString();
        DB::transaction(function () use ($definition, $uid, $key, $activity) {
            abort_if(DB::table('user_task_progress')->where(['task_definition_id' => $definition->id, 'user_id' => $uid, 'period_key' => $key])->whereNotNull('claimed_at')->exists(), 422, '奖励已领取');
            DB::table('user_task_progress')->updateOrInsert(['task_definition_id' => $definition->id, 'user_id' => $uid, 'period_key' => $key], ['progress' => $definition->target, 'completed_at' => now(), 'claimed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $this->rewards->grant($activity->id, $uid, $definition->reward_type, $definition->reward_value, "task-{$definition->id}-{$uid}-{$key}", $definition->name.'奖励');
        });

        return back()->with('success', '任务奖励领取成功');
    }

    public function claimMilestone(Request $request, int $milestone): RedirectResponse
    {
        $activity = $this->activity();
        $m = DB::table('milestone_definitions')->where(['id' => $milestone, 'activity_id' => $activity->id])->firstOrFail();
        $uid = $request->user()->id;
        $laps = DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $uid])->value('completed_laps');
        abort_if($laps < $m->required_laps, 422, '尚未达到里程碑');
        DB::transaction(function () use ($m, $uid, $activity) {
            DB::table('user_milestones')->insert(['milestone_definition_id' => $m->id, 'user_id' => $uid, 'claimed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $this->rewards->grant($activity->id, $uid, $m->reward_type, $m->reward_value, "milestone-{$m->id}-{$uid}", $m->reward_label);
        });

        return back()->with('success', '里程碑宝箱已开启');
    }

    public function useItem(Request $request, int $item): RedirectResponse
    {
        $activity = $this->activity();
        $uid = $request->user()->id;
        $definition = DB::table('item_definitions')->find($item);
        abort_if(! $definition, 404);
        $stock = DB::table('user_items')->where(['user_id' => $uid, 'item_definition_id' => $item])->first();
        abort_if(! $stock || $stock->quantity < 1, 422, '道具数量不足');
        DB::transaction(function () use ($definition, $stock, $uid, $activity) {
            DB::table('user_items')->where('id', $stock->id)->decrement('quantity');
            if ($definition->effect === 'unfreeze') {
                DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $uid])->update(['is_frozen' => false, 'updated_at' => now()]);
            } else {
                DB::table('user_active_effects')->insert(['user_id' => $uid, 'activity_id' => $activity->id, 'effect' => $definition->effect, 'remaining_uses' => 1, 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return back()->with('success', $definition->name.'使用成功');
    }

    public function equipSkin(Request $request, int $skin): RedirectResponse
    {
        abort_unless(DB::table('user_skins')->where(['user_id' => $request->user()->id, 'skin_definition_id' => $skin])->exists(), 403);
        DB::table('users')->where('id', $request->user()->id)->update(['equipped_skin_id' => $skin]);

        return back()->with('success', '棋子皮肤已更换');
    }

    public function submitClaim(Request $request, int $winning): RedirectResponse
    {
        $record = DB::table('winning_records')->where(['id' => $winning, 'user_id' => $request->user()->id])->firstOrFail();
        abort_if($record->status === 'issued', 422, '奖品已经发放');
        $data = $request->validate(['method' => ['required', 'in:wallet,bank,delivery'], 'details' => ['required', 'string', 'max:1000']]);
        DB::table('prize_claims')->updateOrInsert(['winning_record_id' => $record->id], ['user_id' => $request->user()->id, 'method' => $data['method'], 'claim_data' => json_encode(['details' => $data['details']]), 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('winning_records')->where('id', $record->id)->update(['status' => 'submitted', 'updated_at' => now()]);

        return back()->with('success', '领奖资料已提交');
    }

    public function readMessages(Request $request): RedirectResponse
    {
        DB::table('activity_messages')->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);

        return back();
    }

    private function metric(string $metric, object $activity, int $uid, string $period): int
    {
        $start = $period === 'weekly' ? now($activity->timezone)->startOfWeek()->utc() : ($period === 'all' ? $activity->starts_at : now($activity->timezone)->startOfDay()->utc());

        return match ($metric) {
            'checkin' => DB::table('checkins')->where(['activity_id' => $activity->id, 'user_id' => $uid])->where('created_at', '>=', $start)->count(),
            'moves' => DB::table('board_moves')->where(['activity_id' => $activity->id, 'user_id' => $uid, 'action_type' => 'move'])->where('created_at', '>=', $start)->count(),
            'laps' => $period === 'all'
                ? (DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $uid])->value('completed_laps') ?? 0)
                : (int) DB::table('board_moves')->where(['activity_id' => $activity->id, 'user_id' => $uid, 'action_type' => 'move'])->where('created_at', '>=', $start)->selectRaw('COALESCE(SUM(to_lap - from_lap), 0) total')->value('total'),
            'invites' => DB::table('invitation_rewards')->where(['activity_id' => $activity->id, 'inviter_id' => $uid])->where('created_at', '>=', $start)->count(),
            'wins' => DB::table('winning_records')->where(['activity_id' => $activity->id, 'user_id' => $uid])->count(),
            'rank' => (int) (DB::query()->fromSub(DB::table('activity_users')->where('activity_id', $activity->id)->select('user_id')->selectRaw('ROW_NUMBER() OVER (ORDER BY completed_laps DESC, current_position DESC, progress_reached_at ASC) rank'), 'ranked')->where('user_id', $uid)->value('rank') ?? 9999),
            'recharge' => intdiv((int) DB::table('recharge_orders')->where(['activity_id' => $activity->id, 'user_id' => $uid])->where('created_at', '>=', $start)->sum('amount_cents'), 100),
            default => 0
        };
    }

    private function rankings(int $activityId): array
    {
        $progress = DB::table('activity_users')->join('users', 'users.id', '=', 'activity_users.user_id')->where('activity_id', $activityId)->orderByDesc('completed_laps')->orderByDesc('current_position')->limit(20)->get(['users.name', 'completed_laps', 'current_position']);
        $daily = DB::table('board_moves')->join('users', 'users.id', '=', 'board_moves.user_id')->where('activity_id', $activityId)->whereDate('board_moves.created_at', today())->groupBy('users.id', 'users.name')->orderByDesc(DB::raw('COUNT(*)'))->limit(20)->get(['users.name', DB::raw('COUNT(*) score')]);
        $invite = DB::table('invitation_rewards')->join('users', 'users.id', '=', 'invitation_rewards.inviter_id')->where('activity_id', $activityId)->groupBy('users.id', 'users.name')->orderByDesc(DB::raw('COUNT(*)'))->limit(20)->get(['users.name', DB::raw('COUNT(*) score')]);

        return compact('progress', 'daily', 'invite');
    }

    private function syncCollections(object $activity, int $uid, object $state): void
    {
        foreach (DB::table('achievement_definitions')->get() as $achievement) {
            $value = $this->metric($achievement->metric, $activity, $uid, 'all');
            $met = $achievement->metric === 'rank' ? $value <= $achievement->target : $value >= $achievement->target;
            if ($met && ! DB::table('user_achievements')->where(['user_id' => $uid, 'achievement_definition_id' => $achievement->id])->exists()) {
                DB::table('user_achievements')->insert(['user_id' => $uid, 'achievement_definition_id' => $achievement->id, 'unlocked_at' => now(), 'claimed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                if ($achievement->reward_chance) {
                    $this->rewards->grant($activity->id, $uid, 'chance', $achievement->reward_chance, "achievement-{$achievement->id}-{$uid}", $achievement->name.'成就奖励');
                }
            }
        }
        $rank = $this->metric('rank', $activity, $uid, 'all');
        $skins = DB::table('skin_definitions')->where(fn ($q) => $q->where(fn ($x) => $x->where('unlock_type', 'laps')->where('unlock_value', '<=', $state->completed_laps))->orWhere(fn ($x) => $x->where('unlock_type', 'rank')->where('unlock_value', '>=', $rank)))->get();
        foreach ($skins as $skin) {
            DB::table('user_skins')->insertOrIgnore(['user_id' => $uid, 'skin_definition_id' => $skin->id, 'unlocked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function activity(): object
    {
        return DB::table('activities')->where('enabled', true)->firstOrFail();
    }
}
