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
            ->assertSee('最终停在地标时会记录地标访问')
            ->assertSee('常见问题 FAQ');
    }

    public function test_only_real_landmarks_use_landmark_board_markers(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();

        $response = $this->actingAs($user)->get('/activity');

        $response->assertOk()
            ->assertSee('class="cell type-battery category-reward', false)
            ->assertSee('id="soundToggle"', false)
            ->assertSee('id="rollFeedbackModal"', false);
        $this->assertSame(
            DB::table('board_cells')->where('category', 'landmark')->count(),
            substr_count($response->getContent(), 'class="landmark-badge"')
        );
        $this->assertStringNotContainsString(
            '.cell.type-battery{--accent:var(--purple)}',
            file_get_contents(resource_path('css/app.css'))
        );
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

        $requestId = (string) Str::uuid();
        $this->actingAs($user)
            ->postJson('/activity/move', ['request_id' => $requestId])
            ->assertOk()
            ->assertJsonPath('result_text', fn (string $text) => str_contains($text, '新地标印章已解锁'));

        $this->assertSame(1, DB::table('user_landmarks')->where('user_id', $user->id)->count());
        $this->assertSame($before, DB::table('activity_users')->where('user_id', $user->id)->value('chance_balance'));
    }

    public function test_movement_effect_collects_the_final_landmark(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $activityId = DB::table('activities')->value('id');

        foreach (range(1, 6) as $position) {
            DB::table('board_cells')->where(['activity_id' => $activityId, 'position' => $position])->update([
                'type' => 'forward',
                'label' => '前往测试地标',
                'value' => 10 - $position,
                'category' => 'boost',
                'landmark_code' => null,
                'effect_code' => null,
            ]);
        }
        DB::table('board_cells')->where(['activity_id' => $activityId, 'position' => 10])->update([
            'type' => 'normal',
            'label' => '测试终点地标',
            'category' => 'landmark',
            'landmark_code' => 'test_destination',
            'effect_code' => 'lucky_1',
            'description' => '位移后抵达的测试地标',
        ]);
        $landmarkTotal = DB::table('board_cells')->where(['activity_id' => $activityId, 'category' => 'landmark'])->count();

        $requestId = (string) Str::uuid();
        $this->actingAs($user)
            ->postJson('/activity/move', ['request_id' => $requestId])
            ->assertOk()
            ->assertJsonPath('to_position', 10)
            ->assertJsonPath('feedback_type', 'landmark')
            ->assertJsonPath('final_cell_label', '测试终点地标')
            ->assertJsonPath('landmark_unlocked', true)
            ->assertJsonPath('landmark_count', 1)
            ->assertJsonPath('landmark_total', $landmarkTotal);

        $this->actingAs($user)
            ->postJson('/activity/move', ['request_id' => $requestId])
            ->assertOk()
            ->assertJsonPath('landmark_unlocked', true)
            ->assertJsonPath('landmark_count', 1);

        $this->assertSame(1, DB::table('user_landmarks')->where([
            'user_id' => $user->id,
            'board_cell_id' => DB::table('board_cells')->where(['activity_id' => $activityId, 'position' => 10])->value('id'),
        ])->count());
    }

    public function test_movement_destination_does_not_chain_a_battery_reward(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $activityId = DB::table('activities')->value('id');
        $batteryBefore = $user->battery;

        foreach (range(1, 6) as $position) {
            DB::table('board_cells')->where(['activity_id' => $activityId, 'position' => $position])->update([
                'type' => 'forward',
                'label' => '前往电池格',
                'value' => 12 - $position,
                'category' => 'boost',
                'landmark_code' => null,
                'effect_code' => null,
            ]);
        }
        DB::table('board_cells')->where(['activity_id' => $activityId, 'position' => 12])->update([
            'type' => 'battery',
            'label' => '测试电池',
            'value' => 1,
            'category' => 'reward',
        ]);

        $this->actingAs($user)
            ->postJson('/activity/move', ['request_id' => (string) Str::uuid()])
            ->assertOk()
            ->assertJsonPath('to_position', 12)
            ->assertJsonPath('feedback_type', 'boost')
            ->assertJsonPath('final_cell_label', '测试电池')
            ->assertJsonPath('landmark_unlocked', false);

        $this->assertSame($batteryBefore, $user->fresh()->battery);
        $this->assertSame(0, DB::table('winning_records')->where('user_id', $user->id)->count());
    }

    public function test_every_move_category_returns_an_explicit_feedback_contract(): void
    {
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        $activityId = DB::table('activities')->value('id');
        $cases = [
            ['normal', 'normal', 'safe'],
            ['boost', 'forward', 'boost'],
            ['reward', 'chance', 'reward'],
            ['risk', 'freeze', 'risk'],
        ];

        foreach ($cases as [$expected, $type, $category]) {
            DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $user->id])->update([
                'current_position' => 0,
                'is_frozen' => false,
            ]);
            DB::table('board_cells')->where('activity_id', $activityId)->whereBetween('position', [1, 6])->update([
                'type' => $type,
                'label' => "{$expected} feedback",
                'value' => 0,
                'category' => $category,
                'landmark_code' => null,
                'effect_code' => null,
            ]);

            $this->actingAs($user)
                ->postJson('/activity/move', ['request_id' => (string) Str::uuid()])
                ->assertOk()
                ->assertJsonPath('feedback_type', $expected)
                ->assertJsonStructure([
                    'final_cell_label',
                    'landmark_unlocked',
                    'landmark_count',
                    'landmark_total',
                ]);
        }
    }
}
