<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_phone', 30)->nullable()->after('phone_normalized');
            $table->string('whatsapp_phone_normalized', 40)->nullable()->after('whatsapp_phone');
            $table->timestamp('whatsapp_opted_in_at')->nullable()->after('whatsapp_phone_normalized');

            $table->unique('whatsapp_phone_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['whatsapp_phone_normalized']);
            $table->dropColumn([
                'whatsapp_phone',
                'whatsapp_phone_normalized',
                'whatsapp_opted_in_at',
            ]);
        });
    }
};
