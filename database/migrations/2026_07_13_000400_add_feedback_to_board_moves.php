<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_moves', function (Blueprint $table) {
            $table->string('feedback_type')->default('normal');
            $table->string('final_cell_label')->nullable();
            $table->boolean('landmark_unlocked')->default(false);
        });
        DB::table('faq_entries')->where('question', '事件移动后的目标格还会触发吗？')->update([
            'answer' => '最终停在地标时会记录地标访问；其他奖励、风险和移动效果不会再次触发，避免无限连锁。',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('faq_entries')->where('question', '事件移动后的目标格还会触发吗？')->update([
            'answer' => '不会。每次走棋只触发骰子第一次落脚格的事件，避免无限连锁。',
            'updated_at' => now(),
        ]);
        Schema::table('board_moves', function (Blueprint $table) {
            $table->dropColumn(['feedback_type', 'final_cell_label', 'landmark_unlocked']);
        });
    }
};
