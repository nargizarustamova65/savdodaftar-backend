<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('category', 100)->nullable();
            $table->string('barcode', 64)->nullable();
            // dona | kg | g | litr | metr | m2 | qop | quti | pachka | juft | komplekt | boshqa
            $table->string('unit', 20)->default('dona');
            // Tannarx (bo'lmasa foyda hisoblanmaydi)
            $table->decimal('buy_price', 15, 2)->nullable();
            $table->decimal('sell_price', 15, 2);
            // Qoldiq kg/litr uchun kasr bo'lishi mumkin
            $table->decimal('stock', 15, 3)->default(0);
            // Kam qoldiq chegarasi (0 — ogohlantirish yo'q)
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('client_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'name']);
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'barcode']);
            $table->unique(['user_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
