<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Data;

use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Illuminate\Support\Collection;

class AvailabilityResult
{
    /**
     * @param  Collection<int, Vehicle>  $sameCategory
     * @param  array<int, CategoryAvailabilityData>  $otherCategories
     */
    public function __construct(
        public Collection $sameCategory,
        public array $otherCategories,
        public bool $noneAvailable,
    ) {}

    public function hasSameCategoryAlternatives(): bool
    {
        return $this->sameCategory->isNotEmpty();
    }

    public function hasOtherCategoryAlternatives(): bool
    {
        return $this->otherCategories !== [];
    }
}
