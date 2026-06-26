<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('fuhrpark registers driver license relation on user model without host trait', function (): void {
    $userWithLicense = User::factory()->create();
    $userWithoutLicense = User::factory()->create();

    DriverLicense::factory()->create(['user_id' => $userWithLicense->id]);

    expect($userWithLicense->fresh()->driverLicense)->not->toBeNull()
        ->and($userWithoutLicense->driverLicense)->toBeNull()
        ->and(
            User::query()
                ->whereDoesntHave('driverLicense')
                ->whereKey($userWithLicense->id)
                ->exists()
        )->toBeFalse()
        ->and(
            User::query()
                ->whereDoesntHave('driverLicense')
                ->whereKey($userWithoutLicense->id)
                ->exists()
        )->toBeTrue();
});
