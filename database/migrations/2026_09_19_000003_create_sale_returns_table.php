<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // cash | card | debt (qarzdan ayirish)
            $table->string('refund_method', 20);
            $table->decimal('total', 15, 2);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->string('reason', 255)->nullable();
            $table->timestamp('returned_at');
            $table->uuid('client_uuid')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'returned_at']);
            $table->index(['sale_id']);
            $table->unique(['user_id', 'client_uuid']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->decimal('qty', 15, 3);
            $table->decimal('price', 15, 2);
            $table->decimal('buy_price', 15, 2)->nullable();
            $table->decimal('total', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};
