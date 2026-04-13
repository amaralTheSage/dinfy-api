<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->uuid('account_id')->nullable()->change();
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreign('account_id')
                ->references('id')
                ->on('financial_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('financial_transactions')->whereNull('account_id')->exists()) {
            throw new RuntimeException('Cannot revert account_id to required while transactions without an account exist.');
        }

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->uuid('account_id')->nullable(false)->change();
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreign('account_id')
                ->references('id')
                ->on('financial_accounts')
                ->cascadeOnDelete();
        });
    }
};
