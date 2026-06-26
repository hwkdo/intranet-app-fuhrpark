<?php

use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Models\StandortSetting;
use Hwkdo\IntranetAppFuhrpark\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function fuhrparkGrantValidLicense(User $user): DriverLicense
{
    return DriverLicense::factory()->create([
        'user_id' => $user->id,
    ]);
}

function fuhrparkMarkVehicleStandort(Standort $standort, bool $isVehicleStandort = true, ?int $vehicleStandortId = null): StandortSetting
{
    return StandortSetting::query()->updateOrCreate(
        ['standort_id' => $standort->id],
        [
            'is_vehicle_standort' => $isVehicleStandort,
            'vehicle_standort_id' => $vehicleStandortId,
        ],
    );
}
