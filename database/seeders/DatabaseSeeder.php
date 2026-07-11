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
    }
}
