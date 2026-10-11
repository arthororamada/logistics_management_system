<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `pickup_details`
            DROP FOREIGN KEY `pickup_details_pickup_dm_foreign`,
            CHANGE COLUMN `pickup_dm` `dm` BIGINT UNSIGNED NOT NULL,
            ADD CONSTRAINT `pickup_details_dm_foreign` FOREIGN KEY (`dm`) REFERENCES `pickups` (`id`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `pickup_details`
            DROP FOREIGN KEY `pickup_details_dm_foreign`,
            CHANGE COLUMN `dm` `pickup_dm` BIGINT UNSIGNED NOT NULL,
            ADD CONSTRAINT `pickup_details_pickup_dm_foreign` FOREIGN KEY (`pickup_dm`) REFERENCES `pickups` (`id`)');
    }
};
