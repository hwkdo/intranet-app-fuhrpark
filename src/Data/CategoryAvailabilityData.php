<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Data;

use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Illuminate\Support\Collection;

class CategoryAvailabilityData
{
    /**
     * @param  Collection<int, Vehicle>  $vehicles
     */
    public function __construct(
        public VehicleCategory $category,
        public Collection $vehicles,
    ) {}
}
