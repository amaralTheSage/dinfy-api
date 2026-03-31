<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('user_subscriptions', 'provider')) {
            Schema::table('user_subscriptions', function (Blueprint $table) {
                $table->string('provider', 40)->nullable()->default('pix');
            });
        }

        if (!Schema::hasColumn('user_subscriptions', 'provider')) {
            return;
        }

        DB::table('user_subscriptions')
            ->select('id', 'mercado_pago_preapproval_id')
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    DB::table('user_subscriptions')
                        ->where('id', $subscription->id)
                        ->update([
                            'provider' => $subscription->mercado_pago_preapproval_id ? 'mercado_pago' : 'pix',
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('user_subscriptions', 'provider')) {
            return;
        }

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
