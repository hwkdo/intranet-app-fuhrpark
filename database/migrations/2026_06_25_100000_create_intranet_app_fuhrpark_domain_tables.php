<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_fuhrpark_vehicle_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('requires_license')->default(true);
            $table->boolean('is_electric')->default(false);
            $table->unsignedInteger('electric_range_avg_km')->nullable();
            $table->unsignedInteger('electric_charge_minutes_avg')->nullable();
            $table->timestamps();
        });

        Schema::create('intranet_app_fuhrpark_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('intranet_app_fuhrpark_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_category_id')->constrained('intranet_app_fuhrpark_vehicle_categories')->cascadeOnDelete();
            $table->foreignId('standort_id')->constrained('standorts')->cascadeOnDelete();
            $table->string('license_plate');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('vin')->nullable();
            $table->string('fuel_type')->default('petrol');
            $table->unsignedInteger('initial_km')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_new')->default(true);
            $table->string('inactive_reason')->nullable();
            $table->foreignId('inactive_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->unsignedInteger('electric_range_km')->nullable();
            $table->unsignedInteger('electric_charge_minutes')->nullable();
            $table->timestamps();

            $table->unique('license_plate');
        });

        Schema::create('intranet_app_fuhrpark_driver_licenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('valid_until');
            $table->date('restricted_until')->nullable();
            $table->timestamps();
        });

        Schema::create('intranet_app_fuhrpark_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('intranet_app_fuhrpark_vehicles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose')->default('normal');
            $table->string('purpose_note')->nullable();
            $table->string('lock_reason')->nullable();
            $table->foreignId('lock_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('charge_lock_for_booking_id')->nullable();
            $table->text('description');
            $table->boolean('is_commute')->default(false);
            $table->unsignedInteger('electric_route_km')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('km_start')->nullable();
            $table->unsignedInteger('km_end')->nullable();
            $table->string('ms_graph_event_id')->nullable();
            $table->boolean('sync_to_calendar')->default(false);
            $table->timestamps();

            $table->index(['vehicle_id', 'starts_at', 'ends_at'], 'fuhrpark_bookings_vehicle_period_idx');
            $table->index(['driver_id', 'starts_at'], 'fuhrpark_bookings_driver_start_idx');
        });

        Schema::table('intranet_app_fuhrpark_bookings', function (Blueprint $table): void {
            $table->foreign('charge_lock_for_booking_id', 'fuhrpark_bookings_charge_lock_fk')
                ->references('id')
                ->on('intranet_app_fuhrpark_bookings')
                ->nullOnDelete();
        });

        Schema::create('intranet_app_fuhrpark_handouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('intranet_app_fuhrpark_bookings')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('processed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('signature_data')->nullable();
            $table->timestamps();
        });

        Schema::create('intranet_app_fuhrpark_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('handout_id')->unique()->constrained('intranet_app_fuhrpark_handouts')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('processed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('km_end');
            $table->json('checklist')->nullable();
            $table->boolean('has_damage')->default(false);
            $table->text('damage_note')->nullable();
            $table->timestamps();
        });

        Schema::create('intranet_app_fuhrpark_logbook_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('intranet_app_fuhrpark_bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('intranet_app_fuhrpark_projects')->nullOnDelete();
            $table->string('route');
            $table->unsignedInteger('km_commute')->default(0);
            $table->unsignedInteger('km_project')->default(0);
            $table->boolean('fueled')->default(false);
            $table->boolean('cleaned')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_fuhrpark_logbook_entries');
        Schema::dropIfExists('intranet_app_fuhrpark_returns');
        Schema::dropIfExists('intranet_app_fuhrpark_handouts');
        if (Schema::hasTable('intranet_app_fuhrpark_bookings')) {
            Schema::table('intranet_app_fuhrpark_bookings', function (Blueprint $table): void {
                $table->dropForeign('fuhrpark_bookings_charge_lock_fk');
            });
        }
        Schema::dropIfExists('intranet_app_fuhrpark_bookings');
        Schema::dropIfExists('intranet_app_fuhrpark_driver_licenses');
        Schema::dropIfExists('intranet_app_fuhrpark_vehicles');
        Schema::dropIfExists('intranet_app_fuhrpark_projects');
        Schema::dropIfExists('intranet_app_fuhrpark_vehicle_categories');
    }
};
