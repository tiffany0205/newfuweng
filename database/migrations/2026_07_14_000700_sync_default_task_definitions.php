<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities') || ! Schema::hasTable('task_definitions')) {
            return;
        }

        $tasks = [
            ['daily_checkin', '每日签到', '完成今天的连续签到', 'daily', 'checkin', 1, 2],
            ['daily_move', '今日走 5 步', '完成 5 次有效跳棋', 'daily', 'moves', 5, 3],
            ['daily_lap', '完成一圈', '累计完成至少一圈', 'daily', 'laps', 1, 5],
            ['daily_recharge', '今日充值 10U', '累计有效充值达到 10U', 'daily', 'recharge', 10, 5],
            ['weekly_moves', '每周冒险家', '本周完成 20 次跳棋', 'weekly', 'moves', 20, 10],
            ['weekly_invite', '每周召集令', '本周邀请 3 位有效好友', 'weekly', 'invites', 3, 10],
        ];

        foreach (DB::table('activities')->pluck('id') as $activityId) {
            foreach ($tasks as $sort => [$code, $name, $description, $period, $metric, $target, $reward]) {
                if (DB::table('task_definitions')->where(['activity_id' => $activityId, 'code' => $code])->exists()) {
                    continue;
                }

                DB::table('task_definitions')->insert([
                    'activity_id' => $activityId,
                    'code' => $code,
                    'name' => $name,
                    'description' => $description,
                    'period' => $period,
                    'metric' => $metric,
                    'target' => $target,
                    'reward_type' => 'chance',
                    'reward_value' => $reward,
                    'sort_order' => $sort,
                    'enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data repair is intentionally not rolled back.
    }
};
