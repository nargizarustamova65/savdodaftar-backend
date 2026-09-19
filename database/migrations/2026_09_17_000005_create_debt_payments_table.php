<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            // cash | card | other
            $table->string('payment_method', 20)->default('cash');
            $table->string('note', 255)->nullable();
            $table->timestamp('paid_at');
            // Mijoz bo'yicha to'lov bir nechta qarzga taqsimlanganda bir xil uuid bo'ladi
            $table->uuid('client_uuid')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'paid_at']);
            $table->index(['user_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
    }
};
