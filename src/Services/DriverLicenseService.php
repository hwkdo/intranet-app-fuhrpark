<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DriverLicenseService
{
    public function isValid(Authenticatable|Model $user, ?CarbonInterface $onDate = null): bool
    {
        $license = $this->licenseFor($user);

        if (! $license) {
            return false;
        }

        $date = Carbon::parse($onDate ?? now())->startOfDay();

        if ($license->valid_until->startOfDay()->lt($date)) {
            return false;
        }

        if ($license->restricted_until && $license->restricted_until->startOfDay()->lt($date)) {
            return false;
        }

        return true;
    }

    public function canBook(Authenticatable|Model $user, ?int $categoryId = null): bool
    {
        if ($categoryId === null) {
            return true;
        }

        $category = VehicleCategory::query()->find($categoryId);

        if (! $category?->requires_license) {
            return true;
        }

        return $this->isValid($user);
    }

    public function categoryRequiresLicense(?int $categoryId): bool
    {
        if ($categoryId === null) {
            return false;
        }

        return VehicleCategory::query()->find($categoryId)?->requires_license ?? false;
    }

    public function licenseFor(Authenticatable|Model $user): ?DriverLicense
    {
        return DriverLicense::query()->where('user_id', $user->getKey())->first();
    }

    public function isExpiringSoon(Authenticatable|Model $user, int $days = 21): bool
    {
        return app(DriverLicenseControlService::class)->isExpiringSoon($user, $days);
    }
}
