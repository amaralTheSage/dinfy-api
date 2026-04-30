<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('user_subscriptions', 'payer_document_type')) {
                $table->string('payer_document_type', 20)->nullable()->after('frequency_type');
            }

            if (! Schema::hasColumn('user_subscriptions', 'payer_document_number')) {
                $table->string('payer_document_number', 32)->nullable()->after('payer_document_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('user_subscriptions', 'payer_document_type') ? 'payer_document_type' : null,
                Schema::hasColumn('user_subscriptions', 'payer_document_number') ? 'payer_document_number' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
