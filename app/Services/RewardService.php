<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RewardService
{
    public function grant(int $activityId, int $userId, string $type, int $value, string $businessKey, string $label): void
    {
        if ($type === 'chance') {
            if (DB::table('chance_transactions')->where('business_key', $businessKey)->exists()) {
                return;
            }
            DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $userId])->increment('chance_balance', $value);
            $balance = DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $userId])->value('chance_balance');
            DB::table('chance_transactions')->insert(['activity_id' => $activityId, 'user_id' => $userId, 'type' => 'reward', 'amount' => $value, 'balance_after' => $balance, 'business_key' => $businessKey, 'remark' => $label, 'created_at' => now(), 'updated_at' => now()]);
        } elseif ($type === 'item') {
            $item = DB::table('item_definitions')->where('id', $value)->orWhere('code', (string) $value)->first();
            if (! $item) {
                return;
            }
            DB::table('user_items')->insertOrIgnore(['user_id' => $userId, 'item_definition_id' => $item->id, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('user_items')->where(['user_id' => $userId, 'item_definition_id' => $item->id])->increment('quantity');
        } elseif ($type === 'battery') {
            DB::table('users')->where('id', $userId)->increment('battery', $value);
        }
        $this->message($userId, 'reward', '奖励已到账', $label.' 已发放到你的账户', '/activity/center');
    }

    public function message(int $userId, string $type, string $title, string $content, ?string $url = null): void
    {
        DB::table('activity_messages')->insert(['user_id' => $userId, 'type' => $type, 'title' => $title, 'content' => $content, 'action_url' => $url, 'created_at' => now(), 'updated_at' => now()]);
    }
}
