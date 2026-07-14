<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }

    public function index(Request $request): View
    {
        $this->guard($request);
        $activity = DB::table('activities')->where('enabled', true)->firstOrFail();
        $users = DB::table('users')->leftJoin('activity_users', fn ($j) => $j->on('users.id', '=', 'activity_users.user_id')->where('activity_users.activity_id', $activity->id))->select('users.*', 'activity_users.chance_balance', 'activity_users.completed_laps', 'activity_users.current_position')->get();
        $winnings = DB::table('winning_records')
            ->join('users', 'users.id', '=', 'winning_records.user_id')
            ->leftJoin('prize_claims', 'winning_records.id', '=', 'prize_claims.winning_record_id')
            ->where('winning_records.activity_id', $activity->id)
            ->select(
                'winning_records.*',
                'users.name',
                'prize_claims.id as claim_id',
                'prize_claims.method as claim_method',
                'prize_claims.claim_data',
                'prize_claims.status as claim_status',
                'prize_claims.admin_note'
            )
            ->latest('winning_records.id')
            ->limit(50)
            ->get()
            ->map(function ($winning) {
                $claimData = json_decode($winning->claim_data ?? '', true);
                $winning->claim_details = is_array($claimData) ? ($claimData['details'] ?? '—') : '—';

                return $winning;
            });
        $orders = DB::table('recharge_orders')->join('users', 'users.id', '=', 'recharge_orders.user_id')->select('recharge_orders.*', 'users.name')->latest('recharge_orders.id')->limit(50)->get();
        $tasks = DB::table('task_definitions')->where('activity_id', $activity->id)->orderBy('sort_order')->get();
        $metrics = ['users' => DB::table('activity_users')->where('activity_id', $activity->id)->count(), 'moves' => DB::table('board_moves')->where('activity_id', $activity->id)->count(), 'winners' => DB::table('winning_records')->where('activity_id', $activity->id)->count(), 'pending' => DB::table('winning_records')->where('activity_id', $activity->id)->whereNot('status', 'issued')->count()];
        $audits = DB::table('admin_audit_logs')->join('users', 'users.id', '=', 'admin_audit_logs.admin_id')->select('admin_audit_logs.*', 'users.name')->latest('admin_audit_logs.id')->limit(30)->get();

        return view('admin.index', compact('activity', 'users', 'winnings', 'orders', 'tasks', 'metrics', 'audits'));
    }

    public function recharge(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'amount' => ['required', 'numeric', 'min:0.01']]);
        $activity = DB::table('activities')->where('enabled', true)->firstOrFail();
        $cents = (int) round($data['amount'] * 100);
        DB::transaction(function () use ($activity, $data, $cents) {
            $state = DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $data['user_id']])->first();
            $after = $state->recharge_cents + $cents;
            $chance = (intdiv($after, 1000) - intdiv($state->recharge_cents, 1000)) * 10;
            $awardedToday = (int) DB::table('recharge_orders')->where(['activity_id' => $activity->id, 'user_id' => $data['user_id']])->whereDate('created_at', today())->sum('chance_awarded');
            $chance = min($chance, max(0, $activity->paid_chance_daily_cap - $awardedToday));
            $orderNo = 'MANUAL-'.now()->format('YmdHisv').'-'.$data['user_id'];
            DB::table('recharge_orders')->insert(['activity_id' => $activity->id, 'user_id' => $data['user_id'], 'order_no' => $orderNo, 'amount_cents' => $cents, 'chance_awarded' => $chance, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('activity_users')->where('id', $state->id)->update(['recharge_cents' => $after, 'chance_balance' => $state->chance_balance + $chance, 'updated_at' => now()]);
            if ($chance) {
                DB::table('chance_transactions')->insert(['activity_id' => $activity->id, 'user_id' => $data['user_id'], 'type' => 'recharge', 'amount' => $chance, 'balance_after' => $state->chance_balance + $chance, 'business_key' => 'recharge-'.$orderNo, 'remark' => '本人累计充值达标', 'created_at' => now(), 'updated_at' => now()]);
            }
            $invite = DB::table('invitation_rewards')->where(['activity_id' => $activity->id, 'invitee_id' => $data['user_id'], 'recharge_awarded' => false])->first();
            if ($invite && $after >= 1000) {
                DB::table('invitation_rewards')->where('id', $invite->id)->update(['recharge_awarded' => true, 'updated_at' => now()]);
                DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $invite->inviter_id])->increment('chance_balance', 10);
                $balance = DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $invite->inviter_id])->value('chance_balance');
                DB::table('chance_transactions')->insert(['activity_id' => $activity->id, 'user_id' => $invite->inviter_id, 'type' => 'friend_recharge', 'amount' => 10, 'balance_after' => $balance, 'business_key' => 'friend-recharge-'.$data['user_id'], 'remark' => '好友首次充值达标', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('activity_messages')->insert(['user_id' => $invite->inviter_id, 'type' => 'invite', 'title' => '好友首充达标', 'content' => '好友首次累计充值达到 10U，10 次机会已到账。', 'action_url' => '/activity/center#invites', 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return back()->with('success', '充值订单已记账，达标机会已发放');
    }

    public function issue(Request $request, int $winning): RedirectResponse
    {
        $this->guard($request);
        DB::table('winning_records')->where('id', $winning)->update(['status' => 'issued', 'updated_at' => now()]);

        return back()->with('success', '奖品已标记为已发放');
    }

    public function updateActivity(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'paid_chance_daily_cap' => ['required', 'integer', 'min:0', 'max:10000'], 'status' => ['required', 'in:draft,active,frozen,finished']]);
        $activity = DB::table('activities')->where('enabled', true)->firstOrFail();
        DB::table('activities')->where('id', $activity->id)->update($data + ['updated_at' => now()]);
        $this->audit($request, 'activity.update', 'activity', $activity->id, $data);

        return back()->with('success', '活动配置已更新');
    }

    public function adjustChance(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'amount' => ['required', 'integer', 'between:-10000,10000'], 'reason' => ['required', 'string', 'max:200']]);
        $activity = DB::table('activities')->where('enabled', true)->firstOrFail();
        DB::transaction(function () use ($data, $activity) {
            DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $data['user_id']])->increment('chance_balance', $data['amount']);
            $balance = DB::table('activity_users')->where(['activity_id' => $activity->id, 'user_id' => $data['user_id']])->value('chance_balance');
            DB::table('chance_transactions')->insert(['activity_id' => $activity->id, 'user_id' => $data['user_id'], 'type' => 'admin_adjust', 'amount' => $data['amount'], 'balance_after' => $balance, 'business_key' => 'admin-'.uniqid(), 'remark' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->audit($request, 'chance.adjust', 'user', $data['user_id'], $data);

        return back()->with('success', '用户机会已调整');
    }

    public function updateClaim(Request $request, int $claim): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['status' => ['required', 'in:submitted,reviewing,processing,issued,rejected'], 'admin_note' => ['nullable', 'string', 'max:500']]);
        DB::table('prize_claims')->where('id', $claim)->update($data + ['updated_at' => now()]);
        $row = DB::table('prize_claims')->find($claim);
        if ($data['status'] === 'issued') {
            DB::table('winning_records')->where('id', $row->winning_record_id)->update(['status' => 'issued', 'updated_at' => now()]);
        }
        $this->audit($request, 'claim.update', 'prize_claim', $claim, $data);

        return back()->with('success', '领奖状态已更新');
    }

    public function toggleTask(Request $request, int $task): RedirectResponse
    {
        $this->guard($request);
        $current = DB::table('task_definitions')->where('id', $task)->value('enabled');
        DB::table('task_definitions')->where('id', $task)->update(['enabled' => ! $current, 'updated_at' => now()]);
        $this->audit($request, 'task.toggle', 'task', $task, ['enabled' => ! $current]);

        return back()->with('success', '任务状态已更新');
    }

    private function audit(Request $request, string $action, string $type, int|string|null $id, array $data): void
    {
        DB::table('admin_audit_logs')->insert(['admin_id' => $request->user()->id, 'action' => $action, 'target_type' => $type, 'target_id' => $id, 'data' => json_encode($data), 'ip_address' => $request->ip(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
