<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $activityIds = DB::table('activities')->pluck('id');
            if ($activityIds->isEmpty()) {
                return;
            }
            $categories = [
                'normal' => 'safe',
                'start' => 'safe',
                'forward' => 'boost',
                'chance' => 'reward',
                'battery' => 'reward',
                'vip' => 'reward',
                'prize' => 'reward',
                'freeze' => 'risk',
                'bomb' => 'risk',
                'backward' => 'risk',
            ];
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

            foreach ([
                ['reroll', '重掷卡', '🔄', '下一次可以重新掷骰', 'reroll'],
                ['lucky', '幸运卡', '🍀', '下一次免疫负面事件', 'lucky'],
            ] as [$code, $name, $icon, $description, $effect]) {
                DB::table('item_definitions')->updateOrInsert(
                    ['code' => $code],
                    compact('name', 'icon', 'description', 'effect') + ['usable' => true, 'created_at' => $now, 'updated_at' => $now]
                );
            }
            $items = DB::table('item_definitions')->whereIn('code', ['reroll', 'lucky'])->pluck('id', 'code');

            foreach ($activityIds as $activityId) {
                DB::table('board_cells')->where('activity_id', $activityId)->update([
                    'landmark_code' => null,
                    'effect_code' => null,
                    'description' => null,
                ]);
                foreach ($categories as $type => $category) {
                    DB::table('board_cells')->where(['activity_id' => $activityId, 'type' => $type])->update(['category' => $category]);
                }
                foreach ($landmarks as $position => [$code, $effect, $description]) {
                    DB::table('board_cells')->where(['activity_id' => $activityId, 'position' => $position])->update([
                        'category' => 'landmark',
                        'landmark_code' => $code,
                        'effect_code' => $effect,
                        'description' => $description,
                    ]);
                }

                foreach ([
                    [3, '青铜地标宝箱', 'chance', 2, '跳棋机会 +2'],
                    [6, '白银地标宝箱', 'item', (int) $items['reroll'], '重掷卡 ×1'],
                    [9, '黄金地标宝箱', 'chance', 6, '跳棋机会 +6'],
                    [12, '传奇收藏宝箱', 'item', (int) $items['lucky'], '幸运卡 ×1'],
                ] as [$count, $name, $type, $value, $label]) {
                    DB::table('landmark_reward_definitions')->updateOrInsert(
                        ['activity_id' => $activityId, 'required_count' => $count],
                        ['name' => $name, 'reward_type' => $type, 'reward_value' => $value, 'reward_label' => $label, 'created_at' => $now, 'updated_at' => $now]
                    );
                }

                $cells = DB::table('board_cells')->where('activity_id', $activityId)->get()->keyBy('position');
                foreach ($landmarks as $position => [, $effectCode]) {
                    $cell = $cells->get($position);
                    if (! $cell) {
                        continue;
                    }
                    $histories = DB::table('board_moves')
                        ->where(['activity_id' => $activityId, 'action_type' => 'move', 'to_position' => $position])
                        ->groupBy('user_id')
                        ->get([
                            'user_id',
                            DB::raw('COUNT(*) as visits'),
                            DB::raw('MIN(created_at) as first_visit'),
                            DB::raw('MAX(created_at) as last_visit'),
                        ]);
                    foreach ($histories as $history) {
                        $identity = ['activity_id' => $activityId, 'user_id' => $history->user_id, 'board_cell_id' => $cell->id];
                        if (DB::table('user_landmarks')->where($identity)->exists()
                            || ! DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $history->user_id])->exists()) {
                            continue;
                        }
                        $visits = (int) $history->visits;
                        DB::table('user_landmarks')->insert($identity + [
                            'visit_count' => $visits,
                            'first_unlocked_at' => $history->first_visit,
                            'last_visited_at' => $history->last_visit,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $points = max(0, $visits - 1);
                        if (str_starts_with($effectCode, 'lucky_')) {
                            $points += $visits * (int) str_replace('lucky_', '', $effectCode);
                        }
                        if ($points > 0) {
                            DB::table('activity_users')->where(['activity_id' => $activityId, 'user_id' => $history->user_id])->increment('lucky_points', $points);
                        }
                    }
                }

                foreach (DB::table('board_moves')->where('activity_id', $activityId)->get() as $move) {
                    $position = (int) $move->to_position + 1;
                    $label = $move->final_cell_label ?: ($cells->get($move->to_position)->label ?? '当前位置');
                    $remark = $move->action_type === 'unfreeze'
                        ? "解冻成功 · 停留第 {$position} 格 {$label}"
                        : "掷出 {$move->dice_value} 点 · 到达第 {$position} 格 {$label}";
                    DB::table('chance_transactions')->where([
                        'activity_id' => $activityId,
                        'user_id' => $move->user_id,
                        'business_key' => 'move-'.$move->request_id,
                    ])->update(['remark' => $remark, 'updated_at' => $now]);
                }
            }

            foreach ([
                ['棋盘玩法', '什么是地标格？', '紫色地标格可以解锁旅行印章。首次到达解锁地标，重复到达转化为幸运值，部分地标还有轻量增益。', 2],
                ['地标收集', '地标收集会每圈重置吗？', '不会。地标图鉴在整个活动期间累计，每个地标首次解锁后永久记录。', 6],
                ['地标收集', '重复到达已解锁地标会怎样？', '重复地标会增加访问次数并获得 1 点额外幸运值，同时仍会执行该地标的轻量效果。', 7],
            ] as [$category, $question, $answer, $sort]) {
                DB::table('faq_entries')->updateOrInsert(
                    ['question' => $question],
                    ['category' => $category, 'answer' => $answer, 'sort_order' => $sort, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        });
    }

    public function down(): void
    {
        // This migration repairs production data and intentionally does not remove restored progress.
    }
};
