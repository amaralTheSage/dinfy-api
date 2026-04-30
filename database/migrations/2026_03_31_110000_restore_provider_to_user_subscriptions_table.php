<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('user_subscriptions', 'provider')) {
            return;
        }

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->string('provider', 40)->nullable()->default('pix');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('user_subscriptions', 'provider')) {
            return;
        }

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
