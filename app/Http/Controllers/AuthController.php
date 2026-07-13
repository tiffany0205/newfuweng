<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function registerForm(Request $request): View
    {
        return view('auth.register', ['invite' => $request->query('invite')]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => '邮箱或密码错误'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('game.index'));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'invite_code' => ['nullable', 'string', 'exists:users,invite_code'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $inviter = empty($data['invite_code']) ? null : User::where('invite_code', $data['invite_code'])->first();
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
                'invite_code' => strtoupper(Str::random(8)), 'invited_by' => $inviter?->id,
            ]);
            $defaultSkin = DB::table('skin_definitions')->where('unlock_type', 'default')->first();
            if ($defaultSkin) {
                DB::table('user_skins')->insert(['user_id' => $user->id, 'skin_definition_id' => $defaultSkin->id, 'unlocked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                DB::table('users')->where('id', $user->id)->update(['equipped_skin_id' => $defaultSkin->id]);
            }
            DB::table('activity_messages')->insert(['user_id' => $user->id, 'type' => 'welcome', 'title' => '欢迎加入幸运旅程', 'content' => '完成签到和任务获得机会，圈数宝箱与成就奖励等你解锁。', 'action_url' => '/activity/center', 'created_at' => now(), 'updated_at' => now()]);
            $activity = DB::table('activities')->where('enabled', true)->first();
            if ($activity) {
                DB::table('activity_users')->insert(['activity_id' => $activity->id, 'user_id' => $user->id, 'chance_balance' => 0, 'progress_reached_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                if ($inviter) {
                    DB::table('invitation_rewards')->insert(['activity_id' => $activity->id, 'inviter_id' => $inviter->id, 'invitee_id' => $user->id, 'register_awarded' => true, 'created_at' => now(), 'updated_at' => now()]);
                    $this->changeChance($activity->id, $inviter->id, 5, 'invite_register', "invite-register-{$user->id}", '邀请好友注册');
                    DB::table('activity_messages')->insert(['user_id' => $inviter->id, 'type' => 'invite', 'title' => '好友注册成功', 'content' => $user->name.' 已通过你的邀请加入，5 次机会已到账。', 'action_url' => '/activity/center#invites', 'created_at' => now(), 'updated_at' => now()]);
                }
            }

            return $user;
        });
        Auth::login($user);

        return redirect()->route('game.index')->with('success', '注册成功，欢迎参加活动！');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function changeChance(int $activityId, int $userId, int $amount, string $type, string $key, string $remark): void
    {
        DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $userId])->increment('chance_balance', $amount);
        $balance = DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $userId])->value('chance_balance');
        DB::table('chance_transactions')->insert(['activity_id' => $activityId, 'user_id' => $userId, 'type' => $type, 'amount' => $amount, 'balance_after' => $balance, 'business_key' => $key, 'remark' => $remark, 'created_at' => now(), 'updated_at' => now()]);
    }
}
