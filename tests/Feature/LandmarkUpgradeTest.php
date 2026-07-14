<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LandmarkUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_existing_database_repairs_landmarks_history_and_move_remarks_idempotently(): void
    {
        $migrationPath = database_path('migrations/2026_07_14_000500_sync_landmarks_and_move_positions.php');
        $this->assertFileExists($migrationPath);

        $activityId = (int) DB::table('activities')->value('id');
        $user = User::where('email', 'demo@example.com')->firstOrFail();
        DB::table('board_cells')->where('activity_id', $activityId)->update([
            'category' => 'safe',
            'landmark_code' => null,
            'effect_code' => null,
            'description' => null,
        ]);
        DB::table('user_landmarks')->truncate();
        DB::table('landmark_reward_definitions')->truncate();
        DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $user->id])->update(['lucky_points' => 0]);

        $firstRequest = (string) Str::uuid();
        $secondRequest = (string) Str::uuid();
        $this->insertHistoricalMove($activityId, $user->id, $firstRequest, 6, now()->subMinutes(2));
        $this->insertHistoricalMove($activityId, $user->id, $secondRequest, 5, now()->subMinute());

        $migration = require $migrationPath;
        $migration->up();

        $star = DB::table('board_cells')->where(['activity_id' => $activityId, 'position' => 16])->firstOrFail();
        $this->assertSame('landmark', $star->category);
        $this->assertSame('starlight_square', $star->landmark_code);
        $this->assertSame('lucky_2', $star->effect_code);
        $this->assertSame(12, DB::table('board_cells')->where(['activity_id' => $activityId, 'category' => 'landmark'])->count());
        $this->assertSame(4, DB::table('landmark_reward_definitions')->where('activity_id', $activityId)->count());
        $this->assertSame(2, DB::table('user_landmarks')->where(['activity_id' => $activityId, 'user_id' => $user->id, 'board_cell_id' => $star->id])->value('visit_count'));
        $this->assertSame(5, DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $user->id])->value('lucky_points'));
        $this->assertSame(
            '掷出 6 点 · 到达第 17 格 星光广场',
            DB::table('chance_transactions')->where('business_key', 'move-'.$firstRequest)->value('remark')
        );

        $migration->up();

        $this->assertSame(1, DB::table('user_landmarks')->where(['activity_id' => $activityId, 'user_id' => $user->id, 'board_cell_id' => $star->id])->count());
        $this->assertSame(2, DB::table('user_landmarks')->where(['activity_id' => $activityId, 'user_id' => $user->id, 'board_cell_id' => $star->id])->value('visit_count'));
        $this->assertSame(5, DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $user->id])->value('lucky_points'));
    }

    private function insertHistoricalMove(int $activityId, int $userId, string $requestId, int $dice, $createdAt): void
    {
        DB::table('board_moves')->insert([
            'request_id' => $requestId,
            'activity_id' => $activityId,
            'user_id' => $userId,
            'action_type' => 'move',
            'dice_value' => $dice,
            'from_lap' => 0,
            'from_position' => 10,
            'to_lap' => 0,
            'to_position' => 16,
            'cell_type' => 'normal',
            'result_text' => '星光广场',
            'feedback_type' => 'normal',
            'final_cell_label' => '星光广场',
            'landmark_unlocked' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        DB::table('chance_transactions')->insert([
            'activity_id' => $activityId,
            'user_id' => $userId,
            'type' => 'move',
            'amount' => -1,
            'balance_after' => 19,
            'business_key' => 'move-'.$requestId,
            'remark' => '跳棋消耗',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
