<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->insertOrIgnore([
            'name' => 'System Administrator',
            'username' => 'admin',
            'role' => 'administrator',
            'is_active' => true,
            'password' => Hash::make('Admin@12345'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('username', 'admin')->delete();
    }
};
