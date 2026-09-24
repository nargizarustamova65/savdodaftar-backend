<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->timestamps();
            $table->unique(['referrer_id', 'referred_id']);
            $table->index('referred_id');
        });

        Schema::create('bonus_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('type', 32)->default('referral_reward');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'payment_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_transactions');
        Schema::dropIfExists('referrals');
    }
};
