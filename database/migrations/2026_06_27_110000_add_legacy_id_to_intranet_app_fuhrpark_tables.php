<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'intranet_app_fuhrpark_vehicle_categories',
            'intranet_app_fuhrpark_projects',
            'intranet_app_fuhrpark_vehicles',
            'intranet_app_fuhrpark_driver_licenses',
            'intranet_app_fuhrpark_driver_license_controls',
            'intranet_app_fuhrpark_bookings',
            'intranet_app_fuhrpark_handouts',
            'intranet_app_fuhrpark_returns',
            'intranet_app_fuhrpark_logbook_entries',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_id')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'intranet_app_fuhrpark_vehicle_categories',
            'intranet_app_fuhrpark_projects',
            'intranet_app_fuhrpark_vehicles',
            'intranet_app_fuhrpark_driver_licenses',
            'intranet_app_fuhrpark_driver_license_controls',
            'intranet_app_fuhrpark_bookings',
            'intranet_app_fuhrpark_handouts',
            'intranet_app_fuhrpark_returns',
            'intranet_app_fuhrpark_logbook_entries',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('legacy_id');
            });
        }
    }
};
