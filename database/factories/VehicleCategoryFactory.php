<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Database\Factories;

use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleCategory>
 */
class VehicleCategoryFactory extends Factory
{
    protected $model = VehicleCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'requires_license' => true,
            'is_electric' => false,
            'electric_range_avg_km' => null,
            'electric_charge_minutes_avg' => null,
        ];
    }

    public function electric(): static
    {
        return $this->state(fn (): array => [
            'is_electric' => true,
            'electric_range_avg_km' => 250,
            'electric_charge_minutes_avg' => 45,
        ]);
    }
}
