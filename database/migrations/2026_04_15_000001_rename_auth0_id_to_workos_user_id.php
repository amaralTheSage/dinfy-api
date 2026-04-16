<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'auth0_id') && ! Schema::hasColumn('users', 'workos_user_id')) {
            DB::statement('ALTER TABLE users RENAME COLUMN auth0_id TO workos_user_id');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'workos_user_id') && ! Schema::hasColumn('users', 'auth0_id')) {
            DB::statement('ALTER TABLE users RENAME COLUMN workos_user_id TO auth0_id');
        }
    }
};
