<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_fuhrpark_driver_license_controls', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('driver_license_id');
            $table->unsignedBigInteger('inspected_by_user_id');
            $table->foreign('driver_license_id', 'fuhrpark_dl_ctrl_license_fk')
                ->references('id')
                ->on('intranet_app_fuhrpark_driver_licenses')
                ->cascadeOnDelete();
            $table->foreign('inspected_by_user_id', 'fuhrpark_dl_ctrl_inspector_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_fuhrpark_driver_license_controls');
    }
};
