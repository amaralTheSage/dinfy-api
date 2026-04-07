<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_subscriptions')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('user_subscriptions', 'raw_payload') ? 'raw_payload' : null,
                Schema::hasColumn('user_subscriptions', 'latest_payment_payload') ? 'latest_payment_payload' : null,
            ]));

            if ($columns !== []) {
                Schema::table('user_subscriptions', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }

        if (Schema::hasTable('subscription_invoices') && Schema::hasColumn('subscription_invoices', 'raw_payload')) {
            Schema::table('subscription_invoices', function (Blueprint $table) {
                $table->dropColumn('raw_payload');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_subscriptions')) {
            $columnsToRestore = array_values(array_filter([
                ! Schema::hasColumn('user_subscriptions', 'raw_payload') ? 'raw_payload' : null,
                ! Schema::hasColumn('user_subscriptions', 'latest_payment_payload') ? 'latest_payment_payload' : null,
            ]));

            if ($columnsToRestore !== []) {
                Schema::table('user_subscriptions', function (Blueprint $table) use ($columnsToRestore) {
                    if (in_array('raw_payload', $columnsToRestore, true)) {
                        $table->json('raw_payload')->nullable();
                    }

                    if (in_array('latest_payment_payload', $columnsToRestore, true)) {
                        $table->json('latest_payment_payload')->nullable();
                    }
                });
            }
        }

        if (Schema::hasTable('subscription_invoices') && ! Schema::hasColumn('subscription_invoices', 'raw_payload')) {
            Schema::table('subscription_invoices', function (Blueprint $table) {
                $table->json('raw_payload')->nullable();
            });
        }
    }
};
