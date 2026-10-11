<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('prefix', 30)->nullable()->unique()->after('client_name');
            $table->unsignedBigInteger('last_value')->default(0)->after('prefix');
        });

        DB::table('clients')->orderBy('id')->each(function ($client) {
            DB::table('clients')->where('id', $client->id)->update(['prefix' => $client->client_code.'-']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(['prefix', 'last_value']));
    }
};
