<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // rent | transport | salary | ads | electricity | internet | other (TZ 14)
            $table->string('category', 30)->default('other');
            $table->decimal('amount', 15, 2);
            $table->string('note')->nullable();
            $table->date('spent_at');
            $table->uuid('client_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'spent_at']);
            $table->index(['user_id', 'category']);
            $table->unique(['user_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
