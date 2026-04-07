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
        $columns = array_values(array_filter([
            Schema::hasColumn('user_subscriptions', 'plan_name') ? 'plan_name' : null,
            Schema::hasColumn('user_subscriptions', 'reason') ? 'reason' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('user_subscriptions', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->string('plan_name')->default('');
            $table->string('reason')->nullable();
        });
    }
};
