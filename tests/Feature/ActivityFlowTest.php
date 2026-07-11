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

    public function test_move_costs_one_chance_and_is_idempotent(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $requestId = (string) Str::uuid();
        $before = DB::table('activity_users')->where('user_id', $user->id)->value('chance_balance');
        $this->actingAs($user)->postJson('/activity/move', ['request_id' => $requestId])->assertOk()->assertJsonStructure(['dice_value', 'to_position', 'result_text']);
        $this->actingAs($user)->postJson('/activity/move', ['request_id' => $requestId])->assertOk();
        $this->assertSame($before - 1, DB::table('activity_users')->where('user_id', $user->id)->value('chance_balance'));
        $this->assertSame(1, DB::table('board_moves')->where('request_id', $requestId)->count());
    }

    public function test_only_admin_can_open_admin_page(): void
    {
        $this->actingAs(User::where('email', 'demo@example.com')->firstOrFail())->get('/admin')->assertForbidden();
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail())->get('/admin')->assertOk()->assertSee('人工充值记账');
    }
}
