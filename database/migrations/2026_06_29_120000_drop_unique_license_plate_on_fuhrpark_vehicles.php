<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_fuhrpark_vehicles', function (Blueprint $table): void {
            $table->dropUnique(['license_plate']);
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_fuhrpark_vehicles', function (Blueprint $table): void {
            $table->unique('license_plate');
        });
    }
};
