<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            // Tezkor (omborda yo'q) mahsulot uchun null bo'lishi mumkin
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Savdo vaqtidagi nom/birlik (mahsulot keyin o'zgarsa ham chek o'zgarmaydi)
            $table->string('name', 150);
            $table->string('unit', 20)->default('dona');
            $table->decimal('qty', 15, 3);
            $table->decimal('returned_qty', 15, 3)->default(0);
            $table->decimal('price', 15, 2);
            // Savdo vaqtidagi tannarx (foyda hisobi uchun)
            $table->decimal('buy_price', 15, 2)->nullable();
            $table->decimal('total', 15, 2);
            $table->timestamps();

            $table->index(['user_id', 'product_id']);
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
