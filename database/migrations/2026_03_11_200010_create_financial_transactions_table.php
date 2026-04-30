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
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('account_id');

            $table->string('type', 30); // e.g. DEBIT / CREDIT
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('BRL');
            $table->timestamp('occurred_at');

            $table->string('description')->nullable();
            $table->string('merchant')->nullable();
            $table->string('category', 100)->nullable();

            $table->json('data')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')
                ->references('id')
                ->on('financial_accounts')
                ->cascadeOnDelete();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['account_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
