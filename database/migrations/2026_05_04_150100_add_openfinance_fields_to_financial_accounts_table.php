<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->string('openfinance_account_hash')->nullable()->after('data');
            $table->string('openfinance_id')->nullable()->after('openfinance_account_hash');
            $table->text('openfinance_link')->nullable()->after('openfinance_id');
            $table->string('openfinance_status', 50)->nullable()->after('openfinance_link');
            $table->timestamp('openfinance_synced_at')->nullable()->after('openfinance_status');

            $table->index(['user_id', 'openfinance_account_hash']);
            $table->index('openfinance_id');
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'openfinance_account_hash']);
            $table->dropIndex(['openfinance_id']);
            $table->dropColumn([
                'openfinance_account_hash',
                'openfinance_id',
                'openfinance_link',
                'openfinance_status',
                'openfinance_synced_at',
            ]);
        });
    }
};
