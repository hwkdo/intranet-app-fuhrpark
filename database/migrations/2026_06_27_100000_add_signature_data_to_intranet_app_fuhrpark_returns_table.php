<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_fuhrpark_returns', function (Blueprint $table): void {
            $table->json('signature_data')->nullable()->after('damage_note');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_fuhrpark_returns', function (Blueprint $table): void {
            $table->dropColumn('signature_data');
        });
    }
};
