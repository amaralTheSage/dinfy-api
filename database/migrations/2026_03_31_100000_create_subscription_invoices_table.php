<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
        {
                Schema::create('subscription_invoices', function (Blueprint $table) {
                        $table->id();
                        $table->foreignId('user_subscription_id')->constrained('user_subscriptions')->cascadeOnDelete();
                        $table->string('provider', 40)->default('mercado_pago');
                        $table->string('provider_payment_id')->nullable()->index();
                        $table->string('external_reference')->nullable()->index();
                        $table->decimal('transaction_amount', 10, 2);
                        $table->string('currency_id', 8)->default('BRL');
                        $table->string('status', 40)->default('pending')->index();
                        $table->string('status_detail', 120)->nullable();
                        $table->timestamp('expires_at')->nullable();
                        $table->timestamp('paid_at')->nullable();
                        $table->timestamp('canceled_at')->nullable();
                        $table->text('qr_code')->nullable();
                        $table->text('qr_code_base64')->nullable();
                        $table->timestamp('qr_code_expires_at')->nullable();
                        $table->json('raw_payload')->nullable();
                        $table->timestamps();
                });
        }

        public function down(): void
        {
                Schema::dropIfExists('subscription_invoices');
        }
};
