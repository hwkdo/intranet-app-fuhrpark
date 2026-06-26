<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Concerns;

use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Hwkdo\IntranetAppFuhrpark\Services\DriverLicenseService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasFuhrparkRelations
{
    public function driverLicense(): HasOne
    {
        return $this->hasOne(DriverLicense::class, 'user_id');
    }

    public function fuhrparkBookingsAsBooker(): HasMany
    {
        return $this->hasMany(Booking::class, 'user_id');
    }

    public function fuhrparkBookingsAsDriver(): HasMany
    {
        return $this->hasMany(Booking::class, 'driver_id');
    }

    public function canBookFuhrparkVehicle(?int $categoryId = null): bool
    {
        return app(DriverLicenseService::class)->canBook($this, $categoryId)
            && app(BookingStatusResolver::class)->openLogbookCountForDriver($this) < (int) config('intranet-app-fuhrpark.limits.max_open_logbook', 3)
            && app(BookingStatusResolver::class)->noShowCountForDriver($this) < (int) config('intranet-app-fuhrpark.limits.max_no_show', 3);
    }
}
