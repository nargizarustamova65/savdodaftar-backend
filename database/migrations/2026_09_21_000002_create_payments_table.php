<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TZ 36.3: payments — to'lovlar (Payme/Click checkout va webhook holatlari) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('provider', 10); // payme | click
            $table->string('order_id', 40)->unique();
            $table->string('transaction_id', 64)->nullable()->index();
            $table->string('status', 20)->default('pending'); // pending | paid | failed | canceled
            // Payme tranzaksiya holati: 1 (yaratildi), 2 (bajarildi), -1/-2 (bekor)
            $table->integer('provider_state')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            // Provayder vaqtlari (ms) va sabablar: create_time, perform_time, cancel_time, cancel_reason
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
