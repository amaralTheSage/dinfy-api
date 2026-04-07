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
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('pix');
            $table->string('plan_code', 40);
            $table->string('status', 40)->default('pending')->index();
            $table->string('external_reference')->unique();
            $table->string('mercado_pago_payment_id')->nullable();
            $table->decimal('transaction_amount', 10, 2);
            $table->string('currency_id', 8)->default('BRL');
            $table->unsignedInteger('frequency')->default(1);
            $table->string('frequency_type', 20)->default('months');
            $table->string('payer_document_type', 20)->nullable();
            $table->string('payer_document_number', 32)->nullable();
            $table->string('latest_payment_status', 40)->nullable();
            $table->string('latest_payment_status_detail', 120)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('next_payment_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->json('latest_payment_payload')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_provider', 40)->nullable();
            $table->string('subscription_plan', 40)->nullable();
            $table->string('subscription_status', 40)->nullable();
            $table->string('subscription_reference')->nullable();
            $table->timestamp('subscription_started_at')->nullable();
            $table->timestamp('subscription_renews_at')->nullable();
            $table->timestamp('subscription_canceled_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_provider',
                'subscription_plan',
                'subscription_status',
                'subscription_reference',
                'subscription_started_at',
                'subscription_renews_at',
                'subscription_canceled_at',
            ]);
        });

        Schema::dropIfExists('user_subscriptions');
    }
};
