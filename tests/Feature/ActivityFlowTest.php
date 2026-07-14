<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_is_redirected_and_demo_user_can_open_activity(): void
    {
        $this->get('/activity')->assertRedirect('/login');
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $this->actingAs($user)->get('/activity')->assertOk()->assertSee('幸运跳棋大冒险');
    }

    public function test_daily_checkin_awards_five_chances_once(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $before = DB::table('activity_users')->where('user_id', $user->id)->value('chance_balance');
        $this->actingAs($user)->post('/activity/checkin')->assertSessionHas('success');
        $this->assertSame($before + 5, DB::table('activity_users')->where('user_id', $user->id)->value('chance_balance'));
        $this->actingAs($user)->post('/activity/checkin')->assertSessionHas('error');
    }

    public function test_move_returns_actual_position_transaction_and_is_idempotent(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $requestId = (string) Str::uuid();
        $first = $this->actingAs($user)->postJson('/activity/move', ['request_id' => $requestId])
            ->assertOk()
            ->assertJsonStructure(['dice_value', 'to_position', 'display_position', 'result_text', 'lucky_points', 'chance_transaction'])
            ->assertJsonPath('display_position', fn (int $value) => $value >= 1 && $value <= 36)
            ->assertJsonPath('chance_transaction.remark', fn (string $remark) => str_contains($remark, '到达第 '));
        $this->actingAs($user)->postJson('/activity/move', ['request_id' => $requestId])
            ->assertOk()
            ->assertJsonPath('display_position', $first->json('display_position'))
            ->assertJsonPath('chance_transaction.remark', $first->json('chance_transaction.remark'));
        $this->assertSame(-1, DB::table('chance_transactions')->where('business_key', 'move-'.$requestId)->value('amount'));
        $this->assertSame(1, DB::table('chance_transactions')->where('business_key', 'move-'.$requestId)->count());
        $this->assertSame(1, DB::table('board_moves')->where('request_id', $requestId)->count());
    }

    public function test_frozen_move_records_the_actual_stay_position(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $activityId = DB::table('activities')->value('id');
        DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $user->id])->update([
            'current_position' => 16,
            'is_frozen' => true,
        ]);

        $requestId = (string) Str::uuid();
        $this->actingAs($user)->postJson('/activity/move', ['request_id' => $requestId])
            ->assertOk()
            ->assertJsonPath('display_position', 17)
            ->assertJsonPath('final_cell_label', '星光广场')
            ->assertJsonPath('chance_transaction.remark', '解冻成功 · 停留第 17 格 星光广场');
    }

    public function test_activity_renders_premium_dice_stage_and_result_feedback(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        $this->actingAs($user)->get('/activity')
            ->assertOk()
            ->assertSee('dice-stage', false)
            ->assertSee('dice-cube', false)
            ->assertSee('rollResultValue', false)
            ->assertSee('current-position-aura', false);
    }

    public function test_activity_position_display_is_one_based_and_supports_immediate_records(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        DB::table('activity_users')->where('user_id', $user->id)->update(['current_position' => 16]);

        $response = $this->actingAs($user)->get('/activity')->assertOk();
        $response->assertSee('<b class="stat-value position" id="position">17</b>', false)
            ->assertSee('0圈 17格');
        $this->assertStringContainsString(
            'prependChanceRecord(data.chance_transaction)',
            file_get_contents(resource_path('js/app.js'))
        );
    }

    public function test_activity_renders_square_board_with_direct_dice_trigger(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        $this->actingAs($user)->get('/activity')
            ->assertOk()
            ->assertSee('board-square', false)
            ->assertSee('dice-trigger', false)
            ->assertSee('dice-orbit', false)
            ->assertSee('center-statusline', false)
            ->assertSee('event-rail', false)
            ->assertDontSee('class="command-card"', false);
    }

    public function test_activity_renders_shared_task_reward_record_dialog_and_both_triggers(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        $this->actingAs($user)->get('/activity')
            ->assertOk()
            ->assertSee('data-task-records="invite"', false)
            ->assertSee('data-task-records="friend_recharge"', false)
            ->assertSee(route('game.records.task-rewards'), false)
            ->assertSee('id="taskRewardDialog"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('data-task-record-list', false)
            ->assertSee('data-task-record-status', false)
            ->assertSee('data-task-record-more', false)
            ->assertSee('邀请记录')
            ->assertSee('达标记录');
    }

    public function test_activity_renders_compact_ranking_rewards(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        $this->actingAs($user)->get('/activity')
            ->assertOk()
            ->assertSee('ranking-reward-showcase compact', false)
            ->assertSee('iPhone 17 Pro')
            ->assertDontSee('iPhone 16 Pro')
            ->assertSee('images/ranking/iphone-17-pro.svg', false)
            ->assertSee('500 USDT')
            ->assertSee('400 USDT')
            ->assertSee('300 USDT')
            ->assertSee('200 USDT')
            ->assertSee('第 6～10 名')
            ->assertSee('每人 100 USDT');
    }

    public function test_records_are_open_and_initially_render_ten_rows_each(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $activityId = DB::table('activities')->value('id');
        $this->insertRecordFixtures($activityId, $user->id, 12, 'initial');

        $response = $this->actingAs($user)->get('/activity')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString("<details open>\n    <summary>机会明细", $html);
        $this->assertStringContainsString("<details open>\n    <summary>中奖列表", $html);
        $this->assertSame(10, substr_count($html, 'class="chance-record-row"'));
        $this->assertSame(10, substr_count($html, 'class="winning-record-row"'));
    }

    public function test_records_endpoints_return_independent_cursor_pages_for_current_user(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $other = User::where('email', 'admin@example.com')->firstOrFail();
        $activityId = DB::table('activities')->value('id');
        $this->insertRecordFixtures($activityId, $user->id, 25, 'owner');
        $this->insertRecordFixtures($activityId, $other->id, 1, 'other-user-secret');

        $chanceIds = DB::table('chance_transactions')->where(['activity_id' => $activityId, 'user_id' => $user->id])->orderByDesc('id')->pluck('id')->all();
        $winningIds = DB::table('winning_records')->where(['activity_id' => $activityId, 'user_id' => $user->id])->orderByDesc('id')->pluck('id')->all();

        $chanceResponse = $this->actingAs($user)->getJson(route('game.records.chances', ['cursor' => $chanceIds[9]]))
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('next_cursor', $chanceIds[19]);
        $this->assertSame(array_slice($chanceIds, 10, 10), array_column($chanceResponse->json('data'), 'id'));
        $this->assertStringNotContainsString('other-user-secret', $chanceResponse->getContent());

        $winningResponse = $this->actingAs($user)->getJson(route('game.records.winnings', ['cursor' => $winningIds[9]]))
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('next_cursor', $winningIds[19]);
        $this->assertSame(array_slice($winningIds, 10, 10), array_column($winningResponse->json('data'), 'id'));
        $this->assertStringNotContainsString('other-user-secret', $winningResponse->getContent());
    }

    public function test_records_endpoints_reject_invalid_cursors(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        $this->actingAs($user)->getJson('/activity/records/chances?cursor=0')->assertUnprocessable();
        $this->actingAs($user)->getJson('/activity/records/winnings?cursor=0')->assertUnprocessable();
    }

    public function test_task_reward_records_return_private_invite_pages_and_qualified_recharges(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $other = User::where('email', 'admin@example.com')->firstOrFail();
        $activityId = (int) DB::table('activities')->value('id');
        $inviteIds = [];

        for ($index = 1; $index <= 12; $index++) {
            $friend = User::create([
                'name' => "好友{$index}",
                'email' => "friend{$index}@example.com",
                'password' => 'password',
                'invite_code' => "FRIEND{$index}",
                'invited_by' => $user->id,
            ]);
            $createdAt = now()->addMinutes($index);
            $inviteId = DB::table('invitation_rewards')->insertGetId([
                'activity_id' => $activityId,
                'inviter_id' => $user->id,
                'invitee_id' => $friend->id,
                'register_awarded' => true,
                'recharge_awarded' => $index === 12,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $inviteIds[] = $inviteId;
            DB::table('chance_transactions')->insert([
                'activity_id' => $activityId,
                'user_id' => $user->id,
                'type' => 'invite_register',
                'amount' => 5,
                'balance_after' => 100 + $index,
                'business_key' => "invite-register-{$friend->id}",
                'remark' => '邀请好友注册',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            if ($index === 12) {
                DB::table('chance_transactions')->insert([
                    'activity_id' => $activityId,
                    'user_id' => $user->id,
                    'type' => 'friend_recharge',
                    'amount' => 10,
                    'balance_after' => 122,
                    'business_key' => "friend-recharge-{$friend->id}",
                    'remark' => '好友首次充值达标',
                    'created_at' => $createdAt->copy()->addHour(),
                    'updated_at' => $createdAt->copy()->addHour(),
                ]);
            }
        }

        $outsider = User::create(['name' => '秘密好友', 'email' => 'secret@example.com', 'password' => 'password', 'invite_code' => 'SECRET01', 'invited_by' => $other->id]);
        DB::table('invitation_rewards')->insert(['activity_id' => $activityId, 'inviter_id' => $other->id, 'invitee_id' => $outsider->id, 'register_awarded' => true, 'recharge_awarded' => false, 'created_at' => now()->addDay(), 'updated_at' => now()->addDay()]);

        $firstPage = $this->actingAs($user)->getJson(route('game.records.task-rewards', ['type' => 'invite']))
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.friend_name', '好***')
            ->assertJsonPath('data.0.chance_awarded', 5)
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('next_cursor', $inviteIds[2]);
        $this->assertStringNotContainsString('秘密好友', $firstPage->getContent());

        $this->actingAs($user)->getJson(route('game.records.task-rewards', ['type' => 'invite', 'cursor' => $firstPage->json('next_cursor')]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('next_cursor', null);

        $this->actingAs($user)->getJson(route('game.records.task-rewards', ['type' => 'friend_recharge']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.friend_name', '好***')
            ->assertJsonPath('data.0.chance_awarded', 10)
            ->assertJsonPath('has_more', false);
    }

    public function test_task_reward_records_reject_invalid_type_and_cursor(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        $this->actingAs($user)->getJson('/activity/task-reward-records?type=orders')->assertUnprocessable();
        $this->actingAs($user)->getJson('/activity/task-reward-records?type=invite&cursor=0')->assertUnprocessable();
    }

    public function test_only_admin_can_open_admin_page(): void
    {
        $this->actingAs(User::where('email', 'demo@example.com')->firstOrFail())->get('/admin')->assertForbidden();
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail())->get('/admin')->assertOk()->assertSee('活动运营控制台');
    }

    private function insertRecordFixtures(int $activityId, int $userId, int $count, string $prefix): void
    {
        for ($index = 1; $index <= $count; $index++) {
            DB::table('chance_transactions')->insert([
                'activity_id' => $activityId,
                'user_id' => $userId,
                'type' => 'test',
                'amount' => $index,
                'balance_after' => 100 + $index,
                'business_key' => "records-{$prefix}-{$userId}-{$index}",
                'remark' => "{$prefix}-chance-{$index}",
                'created_at' => now()->addSeconds($index),
                'updated_at' => now()->addSeconds($index),
            ]);
            DB::table('winning_records')->insert([
                'activity_id' => $activityId,
                'user_id' => $userId,
                'prize_name' => "{$prefix}-prize-{$index}",
                'status' => $index % 2 === 0 ? 'issued' : 'pending',
                'created_at' => now()->addSeconds($index),
                'updated_at' => now()->addSeconds($index),
            ]);
        }
    }
}
