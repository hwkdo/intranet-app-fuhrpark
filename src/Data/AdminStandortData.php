<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Data;

use Illuminate\Database\Eloquent\Model;

readonly class AdminStandortData
{
    public function __construct(
        public Model $standort,
        public bool $isVehicleStandort,
        public ?int $vehicleStandortId,
    ) {}
}
