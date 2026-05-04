<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->string('openfinance_last_statement_unique_id')->nullable()->after('openfinance_synced_at');
            $table->string('openfinance_statement_status', 50)->nullable()->after('openfinance_last_statement_unique_id');
            $table->text('openfinance_statement_error')->nullable()->after('openfinance_statement_status');
            $table->timestamp('openfinance_last_statement_requested_at')->nullable()->after('openfinance_statement_error');
            $table->timestamp('openfinance_last_statement_checked_at')->nullable()->after('openfinance_last_statement_requested_at');
            $table->timestamp('openfinance_last_statement_result_at')->nullable()->after('openfinance_last_statement_checked_at');
            $table->timestamp('openfinance_next_statement_at')->nullable()->after('openfinance_last_statement_result_at');

            $table->index(['openfinance_statement_status', 'openfinance_next_statement_at']);
            $table->index('openfinance_last_statement_requested_at');
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->string('provider', 40)->nullable()->after('id');
            $table->string('provider_transaction_id', 120)->nullable()->after('provider');

            $table->unique(['account_id', 'provider', 'provider_transaction_id'], 'financial_transactions_provider_unique');
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropUnique('financial_transactions_provider_unique');
            $table->dropIndex(['user_id', 'provider']);
            $table->dropColumn(['provider', 'provider_transaction_id']);
        });

        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropIndex(['openfinance_statement_status', 'openfinance_next_statement_at']);
            $table->dropIndex(['openfinance_last_statement_requested_at']);
            $table->dropColumn([
                'openfinance_last_statement_unique_id',
                'openfinance_statement_status',
                'openfinance_statement_error',
                'openfinance_last_statement_requested_at',
                'openfinance_last_statement_checked_at',
                'openfinance_last_statement_result_at',
                'openfinance_next_statement_at',
            ]);
        });
    }
};
