<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class BookingStoreData extends Data
{
    public function __construct(
        public int $driverId,
        public string $description,
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public ?int $vehicleId = null,
        public ?int $vehicleCategoryId = null,
        public ?int $standortId = null,
        public bool $isCommute = false,
        public ?int $electricRouteKm = null,
        public bool $syncToCalendar = false,
        public ?string $purpose = null,
    ) {}
}
