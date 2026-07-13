<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create(['name' => '活动管理员', 'email' => 'admin@example.com', 'password' => 'Admin123!', 'invite_code' => 'ADMIN888', 'is_admin' => true]);
        $demo = User::create(['name' => '演示用户', 'email' => 'demo@example.com', 'password' => 'Demo123!', 'invite_code' => 'DEMO8888']);
        $activity = DB::table('activities')->insertGetId(['name' => '幸运跳棋大冒险', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(3), 'timezone' => 'Asia/Bangkok', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[$admin->id, 30], [$demo->id, 20]] as [$uid,$chance]) {
            DB::table('activity_users')->insert(['activity_id' => $activity, 'user_id' => $uid, 'chance_balance' => $chance, 'progress_reached_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('chance_transactions')->insert(['activity_id' => $activity, 'user_id' => $uid, 'type' => 'initial', 'amount' => $chance, 'balance_after' => $chance, 'business_key' => "initial-{$uid}", 'remark' => '演示初始机会', 'created_at' => now(), 'updated_at' => now()]);
        }
        $cells = [
            ['start', '起点', '🏁', 0, '#fde68a'], ['normal', '好运启程', '✨', 0, '#fef3c7'], ['forward', '前进3格', '⬆️', 3, '#bbf7d0'], ['normal', '旅行驿站', '🏠', 0, '#e0e7ff'], ['battery', '电池 +1', '🔋', 1, '#cffafe'], ['normal', '阳光大道', '☀️', 0, '#fef9c3'], ['backward', '后退2格', '⬇️', 2, '#fecaca'], ['chance', '机会 +2', '🎲', 2, '#ddd6fe'], ['normal', '彩虹街区', '🌈', 0, '#fae8ff'],
            ['freeze', '冰冻', '🧊', 0, '#bae6fd'], ['normal', '欢乐车站', '🚉', 0, '#e2e8f0'], ['forward', '前进5格', '🚀', 5, '#86efac'], ['prize', '0.01 USDT', '💵', 1, '#fef08a'], ['normal', '幸运转角', '🍀', 0, '#dcfce7'], ['battery', '电池 +1', '🔋', 1, '#cffafe'], ['bomb', '炸弹', '💣', 0, '#fca5a5'], ['normal', '星光广场', '⭐', 0, '#ede9fe'], ['forward', '前进3格', '⬆️', 3, '#bbf7d0'],
            ['vip', 'VIP +1', '👑', 1, '#fde68a'], ['normal', '海滨大道', '🌊', 0, '#cffafe'], ['backward', '后退2格', '⬇️', 2, '#fecaca'], ['freeze', '冰冻', '🧊', 0, '#bae6fd'], ['normal', '音乐小镇', '🎵', 0, '#fce7f3'], ['prize', '50 PHP', '🎁', 50, '#fef08a'], ['chance', '机会 +2', '🎲', 2, '#ddd6fe'], ['normal', '森林公园', '🌳', 0, '#dcfce7'], ['forward', '前进5格', '🚀', 5, '#86efac'],
            ['battery', '电池 +1', '🔋', 1, '#cffafe'], ['normal', '梦想港湾', '⛵', 0, '#dbeafe'], ['bomb', '炸弹', '💣', 0, '#fca5a5'], ['backward', '后退2格', '⬇️', 2, '#fecaca'], ['normal', '金色大道', '🌟', 0, '#fef3c7'], ['prize', '10000 VND', '🧧', 10000, '#fef08a'], ['freeze', '冰冻', '🧊', 0, '#bae6fd'], ['forward', '前进3格', '⬆️', 3, '#bbf7d0'], ['normal', '终点冲刺', '🏆', 0, '#fed7aa'],
        ];
        foreach ($cells as $position => [$type,$label,$icon,$value,$color]) {
            DB::table('board_cells')->insert(['activity_id' => $activity, 'position' => $position, 'type' => $type, 'label' => $label, 'icon' => $icon, 'value' => $value, 'color' => $color, 'created_at' => now(), 'updated_at' => now()]);
        }
        $items = [];
        foreach ([['shield', '防护盾', '🛡️', '抵挡下一次炸弹', 'shield'], ['unfreeze', '解冻卡', '🔥', '无需机会立即解除冰冻', 'unfreeze'], ['double', '双倍卡', '⚡', '下一次骰子点数翻倍', 'double'], ['reroll', '重掷卡', '🔄', '下一次可以重新掷骰', 'reroll'], ['lucky', '幸运卡', '🍀', '下一次免疫负面事件', 'lucky']] as [$code,$name,$icon,$desc,$effect]) {
            $items[$code] = DB::table('item_definitions')->insertGetId(compact('code', 'name', 'icon', 'effect') + ['description' => $desc, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([['daily_checkin', '每日签到', '完成今天的连续签到', 'daily', 'checkin', 1, 2], ['daily_move', '今日走 5 步', '完成 5 次有效跳棋', 'daily', 'moves', 5, 3], ['daily_lap', '完成一圈', '累计完成至少一圈', 'daily', 'laps', 1, 5], ['daily_recharge', '今日充值 10U', '累计有效充值达到 10U', 'daily', 'recharge', 10, 5], ['weekly_moves', '每周冒险家', '本周完成 20 次跳棋', 'weekly', 'moves', 20, 10], ['weekly_invite', '每周召集令', '本周邀请 3 位有效好友', 'weekly', 'invites', 3, 10]] as $sort => [$code,$name,$desc,$period,$metric,$target,$reward]) {
            DB::table('task_definitions')->insert(['activity_id' => $activity, 'code' => $code, 'name' => $name, 'description' => $desc, 'period' => $period, 'metric' => $metric, 'target' => $target, 'reward_type' => 'chance', 'reward_value' => $reward, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([[1, '启程宝箱', 'chance', 2, '跳棋机会 +2'], [3, '青铜宝箱', 'item', $items['shield'], '防护盾 ×1'], [5, '白银宝箱', 'chance', 8, '跳棋机会 +8'], [10, '黄金宝箱', 'item', $items['lucky'], '幸运卡 ×1'], [20, '大师宝箱', 'chance', 20, '跳棋机会 +20'], [50, '传奇宝箱', 'battery', 10, '电池 ×10']] as [$laps,$name,$type,$value,$label]) {
            DB::table('milestone_definitions')->insert(['activity_id' => $activity, 'required_laps' => $laps, 'name' => $name, 'reward_type' => $type, 'reward_value' => $value, 'reward_label' => $label, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([['first_lap', '初次环游', '完成第一圈', '🌍', 'laps', 1, 3], ['ten_laps', '环游达人', '累计完成 10 圈', '🏅', 'laps', 10, 10], ['first_win', '幸运降临', '获得第一个奖品', '💎', 'wins', 1, 3], ['invite_five', '人气召集者', '邀请 5 位有效好友', '🤝', 'invites', 5, 8], ['top_twenty', '榜上有名', '进入排行榜前 20', '🏆', 'rank', 20, 5]] as [$code,$name,$desc,$icon,$metric,$target,$reward]) {
            DB::table('achievement_definitions')->insert(['code' => $code, 'name' => $name, 'description' => $desc, 'icon' => $icon, 'metric' => $metric, 'target' => $target, 'reward_chance' => $reward, 'created_at' => now(), 'updated_at' => now()]);
        }
        $skins = [];
        foreach ([['classic', '经典跑车', '🚗', 'common', 'default', 0], ['airplane', '云端飞行家', '✈️', 'rare', 'laps', 5], ['yacht', '黄金游艇', '🛥️', 'epic', 'laps', 15], ['rocket', '星际探索者', '🚀', 'legendary', 'rank', 10]] as [$code,$name,$icon,$rarity,$type,$value]) {
            $skins[$code] = DB::table('skin_definitions')->insertGetId(['code' => $code, 'name' => $name, 'icon' => $icon, 'rarity' => $rarity, 'unlock_type' => $type, 'unlock_value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([$admin->id, $demo->id] as $uid) {
            DB::table('user_skins')->insert(['user_id' => $uid, 'skin_definition_id' => $skins['classic'], 'unlocked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('users')->where('id', $uid)->update(['equipped_skin_id' => $skins['classic']]);
            DB::table('user_items')->insert(['user_id' => $uid, 'item_definition_id' => $items['shield'], 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('activity_messages')->insert(['user_id' => $uid, 'type' => 'system', 'title' => '幸运中心正式开放', 'content' => '任务、里程碑、道具、成就、皮肤、领奖和多榜单已上线。', 'action_url' => '/activity/center', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
