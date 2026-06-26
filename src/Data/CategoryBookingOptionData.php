<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Data;

use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;

class CategoryBookingOptionData
{
    public function __construct(
        public VehicleCategory $category,
        public bool $isAvailable,
    ) {}

    public function label(): string
    {
        if ($this->isAvailable) {
            return $this->category->name;
        }

        return $this->category->name.' (ausgebucht)';
    }
}
