<?php

use App\Support\PhoneNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_normalized', 40)->nullable()->after('phone');
            $table->index('phone_normalized');
        });

        DB::table('users')
            ->select('id', 'phone')
            ->orderBy('id')
            ->eachById(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'phone_normalized' => PhoneNormalizer::normalize($user->phone),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone_normalized']);
            $table->dropColumn('phone_normalized');
        });
    }
};
