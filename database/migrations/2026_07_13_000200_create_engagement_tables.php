<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('equipped_skin_id')->nullable();
        });
        Schema::table('activities', function (Blueprint $table) {
            $table->string('status')->default('active');
            $table->unsignedInteger('paid_chance_daily_cap')->default(100);
            $table->json('display_config')->nullable();
        });
        Schema::table('winning_records', function (Blueprint $table) {
            $table->unsignedInteger('position')->nullable();
            $table->string('claim_type')->default('auto');
        });

        Schema::create('task_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('period')->default('daily');
            $table->string('metric');
            $table->unsignedInteger('target')->default(1);
            $table->string('reward_type')->default('chance');
            $table->unsignedInteger('reward_value')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['activity_id', 'code']);
        });
        Schema::create('user_task_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_key');
            $table->unsignedInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->unique(['task_definition_id', 'user_id', 'period_key']);
        });
        Schema::create('milestone_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('required_laps');
            $table->string('name');
            $table->string('reward_type');
            $table->unsignedInteger('reward_value');
            $table->string('reward_label');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
        Schema::create('user_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamps();
            $table->unique(['milestone_definition_id', 'user_id']);
        });
        Schema::create('item_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('icon');
            $table->string('description');
            $table->string('effect');
            $table->boolean('usable')->default(true);
            $table->timestamps();
        });
        Schema::create('user_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'item_definition_id']);
        });
        Schema::create('user_active_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('effect');
            $table->unsignedInteger('remaining_uses')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('achievement_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description');
            $table->string('icon');
            $table->string('metric');
            $table->unsignedInteger('target');
            $table->unsignedInteger('reward_chance')->default(0);
            $table->timestamps();
        });
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_definition_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'achievement_definition_id']);
        });
        Schema::create('skin_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('icon');
            $table->string('rarity')->default('common');
            $table->string('unlock_type')->default('default');
            $table->unsignedInteger('unlock_value')->default(0);
            $table->timestamps();
        });
        Schema::create('user_skins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skin_definition_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at');
            $table->timestamps();
            $table->unique(['user_id', 'skin_definition_id']);
        });
        Schema::create('prize_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('winning_record_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->json('claim_data');
            $table->string('status')->default('submitted');
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
        Schema::create('activity_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('system');
            $table->string('title');
            $table->text('content');
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->string('target_type');
            $table->string('target_id')->nullable();
            $table->json('data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['admin_audit_logs', 'activity_messages', 'prize_claims', 'user_skins', 'skin_definitions', 'user_achievements', 'achievement_definitions', 'user_active_effects', 'user_items', 'item_definitions', 'user_milestones', 'milestone_definitions', 'user_task_progress', 'task_definitions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('winning_records', fn (Blueprint $table) => $table->dropColumn(['position', 'claim_type']));
        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn(['status', 'paid_chance_daily_cap', 'display_config']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('equipped_skin_id'));
    }
};
