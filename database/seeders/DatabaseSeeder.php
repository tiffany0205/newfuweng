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
        $categories = ['normal' => 'safe', 'start' => 'safe', 'forward' => 'boost', 'chance' => 'reward', 'battery' => 'reward', 'vip' => 'reward', 'prize' => 'reward', 'freeze' => 'risk', 'bomb' => 'risk', 'backward' => 'risk'];
        foreach ($categories as $type => $category) {
            DB::table('board_cells')->where(['activity_id' => $activity, 'type' => $type])->update(['category' => $category]);
        }
        $landmarks = [
            3 => ['travel_station', 'free_roll', '首次到达解锁旅行驿站印章；到达后返还本次机会。'],
            5 => ['sunshine_road', 'freeze_guard', '首次到达解锁阳光大道印章；获得一次冰冻免疫。'],
            8 => ['rainbow_district', 'rainbow', '首次到达解锁彩虹街区印章；随机获得小惊喜。'],
            10 => ['happy_station', 'free_roll', '首次到达解锁欢乐车站印章；到达后返还本次机会。'],
            13 => ['lucky_corner', 'high_roll', '首次到达解锁幸运转角印章；下一次骰子最低为 4 点。'],
            16 => ['starlight_square', 'lucky_2', '首次到达解锁星光广场印章；获得 2 点幸运值。'],
            19 => ['seaside_road', 'lucky_1', '首次到达解锁海滨大道印章；获得 1 点幸运值。'],
            22 => ['music_town', 'reroll', '首次到达解锁音乐小镇印章；获得一次重掷效果。'],
            25 => ['forest_park', 'shield', '首次到达解锁森林公园印章；首次解锁额外获得防护盾。'],
            28 => ['dream_harbor', 'lucky_2', '首次到达解锁梦想港湾印章；获得 2 点幸运值。'],
            31 => ['golden_road', 'lucky_2', '首次到达解锁金色大道印章；获得 2 点幸运值。'],
            35 => ['finish_sprint', 'free_roll', '首次到达解锁终点冲刺印章；到达后返还本次机会。'],
        ];
        foreach ($landmarks as $position => [$code,$effect,$description]) {
            DB::table('board_cells')->where(['activity_id' => $activity, 'position' => $position])->update(['category' => 'landmark', 'landmark_code' => $code, 'effect_code' => $effect, 'description' => $description]);
        }
        foreach ([[3, '青铜地标宝箱', 'chance', 2, '跳棋机会 +2'], [6, '白银地标宝箱', 'item', $items['reroll'], '重掷卡 ×1'], [9, '黄金地标宝箱', 'chance', 6, '跳棋机会 +6'], [12, '传奇收藏宝箱', 'item', $items['lucky'], '幸运卡 ×1']] as [$count,$name,$type,$value,$label]) {
            DB::table('landmark_reward_definitions')->insert(['activity_id' => $activity, 'required_count' => $count, 'name' => $name, 'reward_type' => $type, 'reward_value' => $value, 'reward_label' => $label, 'created_at' => now(), 'updated_at' => now()]);
        }
        $faqs = [
            ['参与活动', '如何获得跳棋机会？', '可以通过连续签到、每日或每周任务、本人充值、邀请好友、好友首充、圈数宝箱、地标宝箱和成就获得。'],
            ['参与活动', '连续签到中断后如何计算？', '连续签到中断后，下次签到从第 1 天重新计算。第 7、14 天当天共获得 10 次机会。'],
            ['棋盘玩法', '什么是地标格？', '紫色地标格可以解锁旅行印章。首次到达解锁地标，重复到达转化为幸运值，部分地标还有轻量增益。'],
            ['棋盘玩法', '为什么仍然保留安全格？', '安全格用于平衡棋盘节奏，不产生奖励或惩罚。落地后会明确提示安全抵达。'],
            ['棋盘玩法', '事件移动后的目标格还会触发吗？', '不会。每次走棋只触发骰子第一次落脚格的事件，避免无限连锁。'],
            ['棋盘玩法', '炸弹会清除已经完成的圈数吗？', '不会。炸弹只让用户回到当前圈起点，已经完成的圈数不会减少。'],
            ['地标收集', '地标收集会每圈重置吗？', '不会。地标图鉴在整个活动期间累计，每个地标首次解锁后永久记录。'],
            ['地标收集', '重复到达已解锁地标会怎样？', '重复地标会增加访问次数并获得 1 点额外幸运值，同时仍会执行该地标的轻量效果。'],
            ['地标收集', '地标宝箱可以重复领取吗？', '不可以。3、6、9、12 个地标的阶段宝箱，每个阶段只能领取一次。'],
            ['道具', '防护盾和幸运卡有什么区别？', '防护盾只抵挡炸弹；幸运卡可以免疫下一次炸弹或冰冻。'],
            ['道具', '双倍卡可以跨圈吗？', '可以。双倍后的总步数正常计算，超过终点时进入下一圈。'],
            ['充值邀请', '好友分多次充值达到 10U 是否有效？', '有效。好友累计首次达到 10U 时，邀请人获得一次 10 次机会。'],
            ['充值邀请', '好友后续再次充值还有邀请奖励吗？', '没有。每位好友的首充达标奖励只发放一次。'],
            ['排行榜', '相同进度时如何排名？', '先比较圈数，再比较当前格子；仍相同时，较早到达当前进度的用户排名靠前。'],
            ['中奖领奖', '在哪里提交领奖信息？', '进入幸运中心的奖池与领奖中心，根据奖品选择钱包、本地支付或实物配送并提交资料。'],
            ['中奖领奖', '为什么中奖后仍显示待发放？', '资金和实物奖励需要运营审核并真实发放；VIP 和电池等系统奖励可以自动到账。'],
        ];
        foreach ($faqs as $sort => [$category,$question,$answer]) {
            DB::table('faq_entries')->insert(compact('category', 'question', 'answer') + ['sort_order' => $sort, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
