<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('packages')->whereNull('order_number')->update(['order_number' => '']);
        DB::table('packages')->whereNull('package_description')->update(['package_description' => '']);
        DB::table('packages')->whereNull('package_size')->update(['package_size' => '']);
        DB::table('packages')->whereNull('package_length')->update(['package_length' => 0]);
        DB::table('packages')->whereNull('package_width')->update(['package_width' => 0]);
        DB::table('packages')->whereNull('package_height')->update(['package_height' => 0]);
        DB::table('packages')->whereNull('package_weight')->update(['package_weight' => 0]);
        DB::table('packages')->whereNull('payment_type')->update(['payment_type' => '']);
        DB::table('packages')->whereNull('total_price')->update(['total_price' => 0]);
        DB::table('packages')->whereNull('declared_value')->update(['declared_value' => 0]);
        DB::table('packages')->whereNull('buyer_province')->update(['buyer_province' => '']);
        DB::table('packages')->whereNull('buyer_city')->update(['buyer_city' => '']);
        DB::table('packages')->whereNull('buyer_barangay')->update(['buyer_barangay' => '']);

        DB::table('items')->whereNull('item_reference')->update(['item_reference' => '']);
        DB::table('items')->whereNull('item_sku')->update(['item_sku' => '']);
        DB::table('items')->whereNull('item_length')->update(['item_length' => 0]);
        DB::table('items')->whereNull('item_width')->update(['item_width' => 0]);
        DB::table('items')->whereNull('item_height')->update(['item_height' => 0]);
        DB::table('items')->whereNull('item_weight')->update(['item_weight' => 0]);

        Schema::table('packages', function (Blueprint $table) {
            $table->string('order_number', 100)->nullable(false)->change();
            $table->text('package_description')->nullable(false)->change();
            $table->string('package_size', 100)->nullable(false)->change();
            $table->decimal('package_length', 12, 3)->nullable(false)->change();
            $table->decimal('package_width', 12, 3)->nullable(false)->change();
            $table->decimal('package_height', 12, 3)->nullable(false)->change();
            $table->decimal('package_weight', 12, 3)->nullable(false)->change();
            $table->string('payment_type', 100)->nullable(false)->change();
            $table->decimal('total_price', 14, 2)->nullable(false)->change();
            $table->decimal('declared_value', 14, 2)->nullable(false)->change();
            $table->string('buyer_province', 100)->nullable(false)->change();
            $table->string('buyer_city', 100)->nullable(false)->change();
            $table->string('buyer_barangay', 100)->nullable(false)->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('item_reference', 255)->nullable(false)->change();
            $table->string('item_sku', 100)->nullable(false)->change();
            $table->decimal('item_length', 12, 3)->nullable(false)->change();
            $table->decimal('item_width', 12, 3)->nullable(false)->change();
            $table->decimal('item_height', 12, 3)->nullable(false)->change();
            $table->decimal('item_weight', 12, 3)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('order_number', 100)->nullable()->change();
            $table->text('package_description')->nullable()->change();
            $table->string('package_size', 100)->nullable()->change();
            $table->decimal('package_length', 12, 3)->nullable()->change();
            $table->decimal('package_width', 12, 3)->nullable()->change();
            $table->decimal('package_height', 12, 3)->nullable()->change();
            $table->decimal('package_weight', 12, 3)->nullable()->change();
            $table->string('payment_type', 100)->nullable()->change();
            $table->decimal('total_price', 14, 2)->nullable()->change();
            $table->decimal('declared_value', 14, 2)->nullable()->change();
            $table->string('buyer_province', 100)->nullable()->change();
            $table->string('buyer_city', 100)->nullable()->change();
            $table->string('buyer_barangay', 100)->nullable()->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('item_reference', 255)->nullable()->change();
            $table->string('item_sku', 100)->nullable()->change();
            $table->decimal('item_length', 12, 3)->nullable()->change();
            $table->decimal('item_width', 12, 3)->nullable()->change();
            $table->decimal('item_height', 12, 3)->nullable()->change();
            $table->decimal('item_weight', 12, 3)->nullable()->change();
        });
    }
};
