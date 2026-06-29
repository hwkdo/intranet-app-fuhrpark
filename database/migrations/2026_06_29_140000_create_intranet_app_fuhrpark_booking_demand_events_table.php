<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_fuhrpark_booking_demand_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('standort_id')->nullable();
            $table->unsignedBigInteger('vehicle_category_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('reason');
            $table->string('source');
            $table->boolean('had_alternative_category')->default(false);
            $table->timestamps();

            $table->foreign('user_id', 'fuhrpark_bde_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('standort_id', 'fuhrpark_bde_standort_fk')
                ->references('id')->on('standorts')->nullOnDelete();
            $table->foreign('vehicle_category_id', 'fuhrpark_bde_category_fk')
                ->references('id')->on('intranet_app_fuhrpark_vehicle_categories')->nullOnDelete();
            $table->foreign('vehicle_id', 'fuhrpark_bde_vehicle_fk')
                ->references('id')->on('intranet_app_fuhrpark_vehicles')->nullOnDelete();
            $table->foreign('driver_id', 'fuhrpark_bde_driver_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['created_at', 'reason'], 'fuhrpark_bde_created_reason_idx');
            $table->index(['standort_id', 'created_at'], 'fuhrpark_bde_standort_created_idx');
            $table->index(['vehicle_category_id', 'created_at'], 'fuhrpark_bde_category_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_fuhrpark_booking_demand_events');
    }
};
