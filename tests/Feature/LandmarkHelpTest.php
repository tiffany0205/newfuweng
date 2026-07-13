<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LandmarkHelpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authenticated_user_can_open_help_and_landmark_album(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        $this->actingAs($user)
            ->get('/activity/help')
            ->assertOk()
            ->assertSee('地标图鉴')
            ->assertSee('旅行驿站')
            ->assertSee('常见问题 FAQ');
    }

    public function test_tutorial_completion_is_persisted_for_ajax_request(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        $this->actingAs($user)
            ->postJson('/activity/help/tutorial')
            ->assertNoContent();

        $this->assertNotNull(DB::table('activity_users')->where('user_id', $user->id)->value('tutorial_seen_at'));
    }

    public function test_eligible_landmark_reward_can_only_be_claimed_once(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $activityId = DB::table('activities')->value('id');
        $cells = DB::table('board_cells')->where('category', 'landmark')->limit(3)->get();

        foreach ($cells as $cell) {
            DB::table('user_landmarks')->insert([
                'activity_id' => $activityId,
                'user_id' => $user->id,
                'board_cell_id' => $cell->id,
                'visit_count' => 1,
                'first_unlocked_at' => now(),
                'last_visited_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $reward = DB::table('landmark_reward_definitions')->where('required_count', 3)->firstOrFail();
        $before = DB::table('activity_users')->where('user_id', $user->id)->value('chance_balance');

        $this->actingAs($user)
            ->post("/activity/help/landmarks/{$reward->id}/claim")
            ->assertSessionHas('success');

        $this->assertSame($before + 2, DB::table('activity_users')->where('user_id', $user->id)->value('chance_balance'));

        $this->actingAs($user)
            ->post("/activity/help/landmarks/{$reward->id}/claim")
            ->assertStatus(422);
    }

    public function test_landing_on_a_new_landmark_unlocks_stamp_and_applies_effect(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $activityId = DB::table('activities')->value('id');
        DB::table('board_cells')
            ->where('activity_id', $activityId)
            ->whereBetween('position', [1, 6])
            ->update([
                'category' => 'landmark',
                'landmark_code' => 'test_landmark',
                'effect_code' => 'free_roll',
                'description' => '测试地标',
            ]);
        $before = DB::table('activity_users')->where('user_id', $user->id)->value('chance_balance');

        $this->actingAs($user)
            ->postJson('/activity/move', ['request_id' => (string) Str::uuid()])
            ->assertOk()
            ->assertJsonPath('result_text', fn (string $text) => str_contains($text, '新地标印章已解锁'));

        $this->assertSame(1, DB::table('user_landmarks')->where('user_id', $user->id)->count());
        $this->assertSame($before, DB::table('activity_users')->where('user_id', $user->id)->value('chance_balance'));
    }
}
