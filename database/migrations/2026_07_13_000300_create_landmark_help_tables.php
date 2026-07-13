<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_users', function (Blueprint $table) {
            $table->unsignedInteger('lucky_points')->default(0);
            $table->timestamp('tutorial_seen_at')->nullable();
        });
        Schema::table('board_cells', function (Blueprint $table) {
            $table->string('category')->default('safe');
            $table->string('landmark_code')->nullable();
            $table->string('effect_code')->nullable();
            $table->text('description')->nullable();
        });
        Schema::create('user_landmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_cell_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('visit_count')->default(1);
            $table->timestamp('first_unlocked_at');
            $table->timestamp('last_visited_at');
            $table->timestamps();
            $table->unique(['activity_id', 'user_id', 'board_cell_id']);
        });
        Schema::create('landmark_reward_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('required_count');
            $table->string('name');
            $table->string('reward_type');
            $table->unsignedInteger('reward_value');
            $table->string('reward_label');
            $table->timestamps();
            $table->unique(['activity_id', 'required_count']);
        });
        Schema::create('user_landmark_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landmark_reward_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamps();
            $table->unique(['landmark_reward_definition_id', 'user_id']);
        });
        Schema::create('faq_entries', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_entries');
        Schema::dropIfExists('user_landmark_rewards');
        Schema::dropIfExists('landmark_reward_definitions');
        Schema::dropIfExists('user_landmarks');
        Schema::table('board_cells', fn (Blueprint $table) => $table->dropColumn(['category', 'landmark_code', 'effect_code', 'description']));
        Schema::table('activity_users', fn (Blueprint $table) => $table->dropColumn(['lucky_points', 'tutorial_seen_at']));
    }
};
