<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // completed | partially_returned | returned
            $table->string('status', 30)->default('completed');
            // cash | card | debt | mixed
            $table->string('payment_method', 20);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            // To'lov taqsimoti (aralash to'lovda bir nechtasi > 0)
            $table->decimal('paid_cash', 15, 2)->default(0);
            $table->decimal('paid_card', 15, 2)->default(0);
            $table->decimal('debt_amount', 15, 2)->default(0);
            // Foyda hisobi: sotilgan mahsulotlar tannarxi va yalpi foyda
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->default(0);
            // Qaytarishlar yig'indisi (hisobotda ayiriladi)
            $table->decimal('returned_total', 15, 2)->default(0);
            $table->decimal('returned_cost', 15, 2)->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamp('sold_at');
            $table->uuid('client_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'sold_at']);
            $table->index(['user_id', 'status']);
            $table->index(['customer_id', 'sold_at']);
            $table->unique(['user_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
