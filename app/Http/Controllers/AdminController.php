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
        $winnings = DB::table('winning_records')->join('users', 'users.id', '=', 'winning_records.user_id')->select('winning_records.*', 'users.name')->latest('winning_records.id')->limit(50)->get();
        $orders = DB::table('recharge_orders')->join('users', 'users.id', '=', 'recharge_orders.user_id')->select('recharge_orders.*', 'users.name')->latest('recharge_orders.id')->limit(50)->get();

        return view('admin.index', compact('activity', 'users', 'winnings', 'orders'));
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
}
