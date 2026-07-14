<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExperienceCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_open_full_experience_center(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $this->actingAs($user)->get('/activity/center')->assertOk()->assertSee('任务中心')->assertSee('圈数宝箱')->assertSee('邀请好友中心')->assertSee('奖池与领奖中心');
    }

    public function test_experience_center_renders_full_ranking_rewards(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        $this->actingAs($user)->get('/activity/center')
            ->assertOk()
            ->assertSee('ranking-reward-showcase full', false)
            ->assertSee('iPhone 17 Pro')
            ->assertDontSee('iPhone 16 Pro')
            ->assertSee('images/ranking/iphone-17-pro.svg', false)
            ->assertSee('500 USDT')
            ->assertSee('400 USDT')
            ->assertSee('300 USDT')
            ->assertSee('200 USDT')
            ->assertSee('第 6～10 名')
            ->assertSee('每人 100 USDT')
            ->assertSee('最终总进度榜');
    }

    public function test_experience_ranking_positions_are_one_based(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        DB::table('activity_users')->where('user_id', $user->id)->update(['current_position' => 16]);

        $this->actingAs($user)->get('/activity/center')
            ->assertOk()
            ->assertSee('0圈 17格');
    }

    public function test_completed_checkin_task_can_only_be_claimed_once(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $this->actingAs($user)->post('/activity/checkin');
        $task = DB::table('task_definitions')->where('code', 'daily_checkin')->first();
        $before = DB::table('activity_users')->where('user_id', $user->id)->value('chance_balance');
        $this->actingAs($user)->post("/activity/tasks/{$task->id}/claim")->assertSessionHas('success');
        $this->assertSame($before + 2, DB::table('activity_users')->where('user_id', $user->id)->value('chance_balance'));
        $this->actingAs($user)->post("/activity/tasks/{$task->id}/claim")->assertStatus(422);
    }

    public function test_milestone_reward_and_item_usage_change_real_state(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        DB::table('activity_users')->where('user_id', $user->id)->update(['completed_laps' => 3, 'is_frozen' => true]);
        $milestone = DB::table('milestone_definitions')->where('required_laps', 3)->first();
        $this->actingAs($user)->post("/activity/milestones/{$milestone->id}/claim")->assertSessionHas('success');
        $item = DB::table('item_definitions')->where('code', 'unfreeze')->first();
        DB::table('user_items')->insert(['user_id' => $user->id, 'item_definition_id' => $item->id, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->post("/activity/items/{$item->id}/use")->assertSessionHas('success');
        $this->assertFalse((bool) DB::table('activity_users')->where('user_id', $user->id)->value('is_frozen'));
    }

    public function test_non_admin_cannot_use_operations_console(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $this->actingAs($user)->post('/admin/chances', ['user_id' => $user->id, 'amount' => 10, 'reason' => 'test'])->assertForbidden();
    }

    public function test_admin_sees_tasks_and_one_unified_prize_fulfillment_queue(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $activityId = (int) DB::table('activities')->value('id');
        $waitingId = DB::table('winning_records')->insertGetId([
            'activity_id' => $activityId,
            'user_id' => $user->id,
            'prize_name' => '等待资料奖品',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $submittedId = DB::table('winning_records')->insertGetId([
            'activity_id' => $activityId,
            'user_id' => $user->id,
            'prize_name' => '已提交资料奖品',
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('prize_claims')->insert([
            'winning_record_id' => $submittedId,
            'user_id' => $user->id,
            'method' => 'wallet',
            'claim_data' => json_encode(['details' => 'USDT-TEST-ADDRESS']),
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('任务管理（6）')
            ->assertSee('每日签到')
            ->assertSee('奖品发放管理')
            ->assertSee('等待资料奖品')
            ->assertSee('等待用户提交资料')
            ->assertSee(route('admin.winnings.issue', $waitingId), false)
            ->assertSee('已提交资料奖品')
            ->assertSee('USDT-TEST-ADDRESS')
            ->assertDontSee('<h2>领奖审核</h2>', false)
            ->assertDontSee('<h2>中奖发放</h2>', false);
    }
}
