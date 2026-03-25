<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone_normalized', 40)->nullable();
            $table->string('idempotency_key', 191);
            $table->string('intent', 80);
            $table->string('status', 30)->default('processing');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['phone_normalized', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_executions');
    }
};
