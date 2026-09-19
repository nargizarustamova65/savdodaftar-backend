<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // initial | in | out | sale | sale_return | purchase | purchase_return | adjustment
            $table->string('type', 20);
            // Ishorali: musbat — kirim, manfiy — chiqim
            $table->decimal('qty', 15, 3);
            $table->decimal('stock_after', 15, 3);
            // Harakat vaqtidagi tannarx (foyda hisobi uchun)
            $table->decimal('buy_price', 15, 2)->nullable();
            // Savdo/xarid bilan bog'lanish (polymorphic)
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->uuid('client_uuid')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
            $table->index(['product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->unique(['user_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
