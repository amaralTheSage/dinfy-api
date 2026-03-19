<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('financial_budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->decimal('target_amount', 14, 2);
            $table->decimal('current_amount', 14, 2)->default(0);
            $table->string('icon')->nullable();
            $table->string('category', 100)->nullable();
            $table->char('currency', 3)->default('BRL');
            $table->json('data')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_budgets');
    }
};
