<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskDefinitionUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_existing_database_receives_missing_default_tasks_idempotently(): void
    {
        $migrationPath = database_path('migrations/2026_07_14_000700_sync_default_task_definitions.php');
        $this->assertFileExists($migrationPath);

        $activityId = (int) DB::table('activities')->value('id');
        DB::table('task_definitions')->truncate();
        DB::table('task_definitions')->insert([
            'activity_id' => $activityId,
            'code' => 'daily_checkin',
            'name' => '自定义签到任务',
            'period' => 'daily',
            'metric' => 'checkin',
            'target' => 9,
            'reward_type' => 'chance',
            'reward_value' => 99,
            'sort_order' => 99,
            'enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require $migrationPath;
        $migration->up();
        $migration->up();

        $this->assertSame(6, DB::table('task_definitions')->where('activity_id', $activityId)->count());
        $custom = DB::table('task_definitions')->where(['activity_id' => $activityId, 'code' => 'daily_checkin'])->firstOrFail();
        $this->assertSame('自定义签到任务', $custom->name);
        $this->assertSame(99, $custom->reward_value);
        $this->assertFalse((bool) $custom->enabled);
    }
}
