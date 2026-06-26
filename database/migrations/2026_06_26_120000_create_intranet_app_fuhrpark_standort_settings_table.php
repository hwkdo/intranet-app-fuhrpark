<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_fuhrpark_standort_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standort_id')->unique();
            $table->boolean('is_vehicle_standort')->default(false);
            $table->foreignId('vehicle_standort_id')->nullable();
            $table->timestamps();

            $table->foreign('standort_id', 'fuhrpark_standort_setting_standort_fk')
                ->references('id')
                ->on('standorts')
                ->cascadeOnDelete();

            $table->foreign('vehicle_standort_id', 'fuhrpark_standort_setting_vehicle_fk')
                ->references('id')
                ->on('standorts')
                ->nullOnDelete();
        });

        if (! Schema::hasTable('standorts') || ! Schema::hasColumn('standorts', 'fahrzeugstandort_id')) {
            return;
        }

        $now = now();

        DB::table('standorts')
            ->orderBy('id')
            ->get(['id', 'fahrzeugstandort_id'])
            ->each(function (object $standort) use ($now): void {
                DB::table('intranet_app_fuhrpark_standort_settings')->insert([
                    'standort_id' => $standort->id,
                    'is_vehicle_standort' => $standort->fahrzeugstandort_id === null,
                    'vehicle_standort_id' => $standort->fahrzeugstandort_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_fuhrpark_standort_settings');
    }
};
