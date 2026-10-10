<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('slug', 'dashboard.view')
                ->update(['description' => 'Access the fleet operations dashboard.']);
        }

        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('slug', 'staff')
                ->update(['description' => 'Basic access for fleet operations staff.']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('slug', 'dashboard.view')
                ->update(['description' => 'Access the school dashboard.']);
        }

        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('slug', 'staff')
                ->update(['description' => 'Basic access for school staff members.']);
        }
    }
};
