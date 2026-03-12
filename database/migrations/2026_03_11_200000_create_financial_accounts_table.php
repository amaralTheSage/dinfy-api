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
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 50);
            $table->string('subtype', 50)->nullable();

            $table->string('name');
            $table->string('marketing_name')->nullable();
            $table->string('tax_number', 30)->nullable();
            $table->string('owner')->nullable();
            $table->string('number_last4', 4)->nullable();

            $table->decimal('balance', 14, 2)->default(0);
            $table->char('currency', 3)->default('BRL');

            $table->json('credit_data')->nullable();
            $table->json('data')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};

