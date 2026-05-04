<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->default('openfinance');
            $table->string('zipcode', 20);
            $table->string('street')->nullable();
            $table->string('neighborhood');
            $table->string('address_number', 30);
            $table->string('address_complement')->nullable();
            $table->char('state', 2);
            $table->string('city');
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
