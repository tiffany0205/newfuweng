<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone')->default('Asia/Bangkok');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('activity_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('chance_balance')->default(0);
            $table->unsignedInteger('completed_laps')->default(0);
            $table->unsignedTinyInteger('current_position')->default(0);
            $table->boolean('is_frozen')->default(false);
            $table->timestamp('progress_reached_at')->nullable();
            $table->string('ranking_status')->default('eligible');
            $table->unsignedInteger('recharge_cents')->default(0);
            $table->timestamps();
            $table->unique(['activity_id', 'user_id']);
            $table->index(['activity_id', 'completed_laps', 'current_position']);
        });

        Schema::create('board_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('type');
            $table->string('label');
            $table->string('icon')->default('·');
            $table->integer('value')->default(0);
            $table->string('color')->default('#ffffff');
            $table->timestamps();
            $table->unique(['activity_id', 'position']);
        });

        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checkin_date');
            $table->unsignedInteger('streak_day');
            $table->unsignedInteger('chance_awarded');
            $table->timestamps();
            $table->unique(['activity_id', 'user_id', 'checkin_date']);
        });

        Schema::create('chance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->integer('amount');
            $table->integer('balance_after');
            $table->string('business_key')->unique();
            $table->string('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('board_moves', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_type');
            $table->unsignedTinyInteger('dice_value')->nullable();
            $table->unsignedInteger('from_lap');
            $table->unsignedTinyInteger('from_position');
            $table->unsignedInteger('to_lap');
            $table->unsignedTinyInteger('to_position');
            $table->string('cell_type')->nullable();
            $table->string('result_text')->nullable();
            $table->timestamps();
        });

        Schema::create('winning_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_move_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prize_name');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('recharge_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_no')->unique();
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('chance_awarded');
            $table->string('status')->default('paid');
            $table->timestamps();
        });

        Schema::create('invitation_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitee_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('register_awarded')->default(false);
            $table->boolean('recharge_awarded')->default(false);
            $table->timestamps();
            $table->unique(['activity_id', 'invitee_id']);
        });
    }

    public function down(): void
    {
        foreach (['invitation_rewards', 'recharge_orders', 'winning_records', 'board_moves', 'chance_transactions', 'checkins', 'board_cells', 'activity_users', 'activities'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
