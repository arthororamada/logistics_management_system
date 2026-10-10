<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('waybill_number', 100)->unique();
            $table->string('order_number', 100)->nullable();
            $table->string('package_id', 100)->unique();
            $table->string('package_location', 255)->nullable();
            $table->string('package_status', 100)->default('for_pickup');
            $table->text('package_description')->nullable();
            $table->string('package_sku', 100)->nullable();
            $table->string('package_size', 100)->nullable();
            $table->unsignedInteger('package_quantity')->default(1);
            $table->decimal('package_length', 12, 3)->nullable();
            $table->decimal('package_width', 12, 3)->nullable();
            $table->decimal('package_height', 12, 3)->nullable();
            $table->decimal('package_weight', 12, 3)->nullable();
            $table->string('payment_type', 100)->nullable();
            $table->decimal('total_price', 14, 2)->nullable();
            $table->decimal('declared_value', 14, 2)->nullable();
            $table->text('package_remarks')->nullable();
            foreach (range(1, 5) as $reference) $table->string("package_reference_{$reference}", 255)->nullable();
            $table->string('buyer_name', 255);
            $table->string('buyer_mobile', 50);
            $table->string('buyer_email')->nullable();
            $table->text('buyer_full_address');
            $table->string('buyer_province', 100)->nullable();
            $table->string('buyer_city', 100)->nullable();
            $table->string('buyer_barangay', 100)->nullable();
            $table->decimal('buyer_long', 11, 8)->nullable();
            $table->decimal('buyer_lat', 10, 8)->nullable();
            $table->string('buyer_building_type', 100)->nullable();
            $table->foreignId('seller_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->dateTime('pickup_date')->nullable();
            $table->dateTime('handover_date')->nullable();
            $table->string('remittance_status', 100)->nullable();
            $table->dateTime('delivery_min_time')->nullable();
            $table->dateTime('delivery_max_time')->nullable();
            $table->boolean('is_priority')->default(false);
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_deleted')->default(false);
            $table->index(['package_status', 'is_deleted']);
            $table->index(['buyer_province', 'buyer_city', 'buyer_barangay']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('waybill_number', 100);
            $table->string('item_reference', 255)->nullable();
            $table->text('item_description');
            $table->string('item_sku', 100)->nullable();
            $table->decimal('item_length', 12, 3)->nullable();
            $table->decimal('item_width', 12, 3)->nullable();
            $table->decimal('item_height', 12, 3)->nullable();
            $table->decimal('item_weight', 12, 3)->nullable();
            $table->text('item_remarks')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_deleted')->default(false);
            $table->foreign('waybill_number')->references('waybill_number')->on('packages')->cascadeOnUpdate()->cascadeOnDelete();
            $table->index(['waybill_number', 'is_deleted']);
        });

        Schema::table('packages', fn (Blueprint $table) => $table->foreign('item_id')->references('id')->on('items')->nullOnDelete());

        $now = now();
        $permissions = collect([
            ['name' => 'View Waybills', 'slug' => 'waybills.view', 'description' => 'View waybill and package records.'],
            ['name' => 'Create Waybills', 'slug' => 'waybills.create', 'description' => 'Create waybills, packages, and items.'],
            ['name' => 'Upload Waybills', 'slug' => 'waybills.upload', 'description' => 'Mass upload waybill, package, and item records.'],
        ])->map(fn ($permission) => [...$permission, 'group' => 'Waybill Management', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('permissions')->insert($permissions->all());
        $roleId = DB::table('roles')->where('slug', 'administrator')->value('id');
        if ($roleId) DB::table('permission_role')->insert(DB::table('permissions')->whereIn('slug', $permissions->pluck('slug'))->pluck('id')->map(fn ($id) => ['permission_id' => $id, 'role_id' => $roleId])->all());
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['waybills.view', 'waybills.create', 'waybills.upload'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::table('packages', fn (Blueprint $table) => $table->dropForeign(['item_id']));
        Schema::dropIfExists('items');
        Schema::dropIfExists('packages');
    }
};
