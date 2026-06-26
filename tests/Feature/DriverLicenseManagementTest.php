<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicenseControl;
use Hwkdo\IntranetAppFuhrpark\Services\DriverLicenseControlService;
use Hwkdo\IntranetAppFuhrpark\Services\DriverLicenseService;
use Hwkdo\IntranetAppFuhrpark\Tasks\DriverLicenseTaskProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::findOrCreate('see-app-fuhrpark', 'web');
    Permission::findOrCreate('manage-app-fuhrpark-driver-licenses', 'web');
});

test('driver licenses route is accessible with permission', function (): void {
    $user = User::factory()->create(['active' => true]);
    $user->givePermissionTo('manage-app-fuhrpark-driver-licenses');

    $this->actingAs($user)
        ->get(route('apps.fuhrpark.fuehrerscheine'))
        ->assertOk()
        ->assertSeeText('Führerscheine');
});

test('driver licenses route is forbidden without permission', function (): void {
    $user = User::factory()->create(['active' => true]);
    $user->givePermissionTo('see-app-fuhrpark');

    $this->actingAs($user)
        ->get(route('apps.fuhrpark.fuehrerscheine'))
        ->assertForbidden();
});

test('initial control creates license and control record', function (): void {
    Carbon::setTestNow('2026-06-26');

    $inspector = User::factory()->create(['active' => true]);
    $driver = User::factory()->create(['active' => true]);

    $control = app(DriverLicenseControlService::class)->recordInitialControl(
        user: $driver,
        inspector: $inspector,
        restrictedUntil: Carbon::parse('2028-01-01'),
        note: 'Erstkontrolle durchgeführt',
    );

    $license = DriverLicense::query()->where('user_id', $driver->id)->first();

    expect($license)->not->toBeNull()
        ->and($license->valid_until->toDateString())->toBe('2027-06-26')
        ->and($license->restricted_until?->toDateString())->toBe('2028-01-01')
        ->and($control->driver_license_id)->toBe($license->id)
        ->and($control->inspected_by_user_id)->toBe($inspector->id)
        ->and($control->note)->toBe('Erstkontrolle durchgeführt');
});

test('follow up control extends valid until by one year', function (): void {
    Carbon::setTestNow('2026-06-26');

    $inspector = User::factory()->create(['active' => true]);
    $license = DriverLicense::factory()->create([
        'valid_until' => '2026-01-01',
        'restricted_until' => null,
    ]);

    app(DriverLicenseControlService::class)->recordFollowUpControl(
        license: $license,
        inspector: $inspector,
        note: 'Folgekontrolle',
    );

    $license->refresh();

    expect($license->valid_until->toDateString())->toBe('2027-06-26')
        ->and($license->controls()->count())->toBe(1);
});

test('extend one year creates control with system note', function (): void {
    Carbon::setTestNow('2026-06-26');

    $inspector = User::factory()->create(['active' => true]);
    $license = DriverLicense::factory()->create([
        'valid_until' => '2026-03-01',
    ]);

    $control = app(DriverLicenseControlService::class)->extendOneYear($license, $inspector);

    $license->refresh();

    expect($license->valid_until->toDateString())->toBe('2027-06-26')
        ->and($control->note)->toBe('Automatische Verlängerung (+1 Jahr)');
});

test('is expiring soon detects valid until within 21 days', function (): void {
    Carbon::setTestNow('2026-06-26');

    $user = User::factory()->create(['active' => true]);
    DriverLicense::factory()->create([
        'user_id' => $user->id,
        'valid_until' => '2026-07-10',
        'restricted_until' => null,
    ]);

    expect(app(DriverLicenseControlService::class)->isExpiringSoon($user))->toBeTrue()
        ->and(app(DriverLicenseService::class)->isExpiringSoon($user))->toBeTrue();
});

test('is expiring soon detects restricted until within 21 days', function (): void {
    Carbon::setTestNow('2026-06-26');

    $user = User::factory()->create(['active' => true]);
    DriverLicense::factory()->create([
        'user_id' => $user->id,
        'valid_until' => '2027-06-26',
        'restricted_until' => '2026-07-05',
    ]);

    expect(app(DriverLicenseControlService::class)->isExpiringSoon($user))->toBeTrue();
});

test('task provider returns task for invalid license', function (): void {
    $user = User::factory()->create(['active' => true]);

    $tasks = app(DriverLicenseTaskProvider::class)->getTasksForUser($user);

    expect($tasks)->toHaveCount(1)
        ->and($tasks->first()->description)->toBe('Kein gültiger Führerschein hinterlegt');
});

test('task provider returns task when license is expiring soon', function (): void {
    Carbon::setTestNow('2026-06-26');

    $user = User::factory()->create(['active' => true]);
    DriverLicense::factory()->create([
        'user_id' => $user->id,
        'valid_until' => '2026-07-10',
    ]);

    $tasks = app(DriverLicenseTaskProvider::class)->getTasksForUser($user);

    expect($tasks)->toHaveCount(1)
        ->and($tasks->first()->description)->toContain('läuft in Kürze ab');
});

test('task provider returns no task for valid license', function (): void {
    Carbon::setTestNow('2026-06-26');

    $user = User::factory()->create(['active' => true]);
    DriverLicense::factory()->create([
        'user_id' => $user->id,
        'valid_until' => '2027-06-26',
    ]);

    $tasks = app(DriverLicenseTaskProvider::class)->getTasksForUser($user);

    expect($tasks)->toBeEmpty();
});

test('driver license control download requires permission', function (): void {
    Storage::fake('local');

    $inspector = User::factory()->create(['active' => true]);
    $license = DriverLicense::factory()->create();
    $control = DriverLicenseControl::factory()->create([
        'driver_license_id' => $license->id,
        'inspected_by_user_id' => $inspector->id,
        'file_path' => 'fuhrpark/driver-license-controls/1/scan.pdf',
        'file_name' => 'scan.pdf',
    ]);

    Storage::disk('local')->put($control->file_path, 'pdf-content');

    $unauthorized = User::factory()->create(['active' => true]);
    $unauthorized->givePermissionTo('see-app-fuhrpark');

    $this->actingAs($unauthorized)
        ->get(route('apps.fuhrpark.driver-license-controls.download', $control))
        ->assertForbidden();

    $authorized = User::factory()->create(['active' => true]);
    $authorized->givePermissionTo('manage-app-fuhrpark-driver-licenses');

    $this->actingAs($authorized)
        ->get(route('apps.fuhrpark.driver-license-controls.download', $control))
        ->assertOk();
});

test('livewire extend one year action updates license', function (): void {
    Carbon::setTestNow('2026-06-26');

    $manager = User::factory()->create(['active' => true]);
    $manager->givePermissionTo('manage-app-fuhrpark-driver-licenses');

    $license = DriverLicense::factory()->create([
        'valid_until' => '2026-01-01',
    ]);

    Livewire::actingAs($manager)
        ->test('intranet-app-fuhrpark::apps.fuhrpark.driver-licenses')
        ->call('extendOneYear', $license->id)
        ->assertHasNoErrors();

    expect($license->fresh()->valid_until->toDateString())->toBe('2027-06-26');
});

test('initial control stores uploaded scan', function (): void {
    Storage::fake('local');
    Carbon::setTestNow('2026-06-26');

    $inspector = User::factory()->create(['active' => true]);
    $driver = User::factory()->create(['active' => true]);
    $file = UploadedFile::fake()->create('fuehrerschein.pdf', 100, 'application/pdf');

    $control = app(DriverLicenseControlService::class)->recordInitialControl(
        user: $driver,
        inspector: $inspector,
        file: $file,
    );

    expect($control->file_name)->toBe('fuehrerschein.pdf')
        ->and(Storage::disk('local')->exists($control->file_path))->toBeTrue();
});
