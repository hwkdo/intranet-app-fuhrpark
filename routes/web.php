<?php

use Hwkdo\IntranetAppFuhrpark\Http\Controllers\DriverLicenseControlDownloadController;
use Hwkdo\IntranetAppFuhrpark\Http\Controllers\VehicleLogbookPdfController;
use Hwkdo\IntranetAppFuhrpark\Livewire\Calendar;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['web', 'auth', 'can:see-app-fuhrpark'])->group(function (): void {
    Route::livewire('apps/fuhrpark', Calendar::class)->name('apps.fuhrpark.index');
    Volt::route('apps/fuhrpark/meine', 'apps.fuhrpark.my-bookings')->name('apps.fuhrpark.meine');
    Volt::route('apps/fuhrpark/info', 'apps.fuhrpark.info')->name('apps.fuhrpark.info');
    Volt::route('apps/fuhrpark/settings/user', 'apps.fuhrpark.settings.user')->name('apps.fuhrpark.settings.user');
});

Route::middleware(['web', 'auth', 'can:fuhrpark.manage-driver-licenses'])->group(function (): void {
    Route::livewire('apps/fuhrpark/fuehrerscheine', 'intranet-app-fuhrpark::apps.fuhrpark.driver-licenses')
        ->name('apps.fuhrpark.fuehrerscheine');

    Route::get('apps/fuhrpark/fuehrerscheine/kontrollen/{control}/download', DriverLicenseControlDownloadController::class)
        ->name('apps.fuhrpark.driver-license-controls.download');
});

Route::middleware(['web', 'auth', 'can:fuhrpark.view-team'])->group(function (): void {
    Volt::route('apps/fuhrpark/team', 'apps.fuhrpark.team-bookings')->name('apps.fuhrpark.team');
});

Route::middleware(['web', 'auth', 'can:fuhrpark.operate-desk'])->group(function (): void {
    Volt::route('apps/fuhrpark/zentrale', 'apps.fuhrpark.desk')->name('apps.fuhrpark.zentrale');
});

Route::middleware(['web', 'auth', 'can:manage-app-fuhrpark'])->group(function (): void {
    Volt::route('apps/fuhrpark/admin', 'apps.fuhrpark.admin.index')->name('apps.fuhrpark.admin.index');

    Route::get('apps/fuhrpark/admin/vehicles/{vehicle}/fahrtenbuch.pdf', VehicleLogbookPdfController::class)
        ->name('apps.fuhrpark.admin.vehicles.logbook-pdf');

    Route::redirect('apps/fuhrpark/admin/vehicles', '/apps/fuhrpark/admin?tab=fahrzeuge')->name('apps.fuhrpark.admin.vehicles');
    Route::redirect('apps/fuhrpark/admin/categories', '/apps/fuhrpark/admin?tab=kategorien')->name('apps.fuhrpark.admin.categories');
    Route::redirect('apps/fuhrpark/admin/projects', '/apps/fuhrpark/admin?tab=projekte')->name('apps.fuhrpark.admin.projects');
    Route::redirect('apps/fuhrpark/admin/bookings', '/apps/fuhrpark/admin?tab=buchungen')->name('apps.fuhrpark.admin.bookings');
    Route::redirect('apps/fuhrpark/admin/standorte', '/apps/fuhrpark/admin?tab=standorte')->name('apps.fuhrpark.admin.standorte');
});
