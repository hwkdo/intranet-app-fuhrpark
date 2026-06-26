<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Database\Factories;

use App\Models\Standort;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-import-type Standort from \App\Models\Standort
 */

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'vehicle_category_id' => VehicleCategory::factory(),
            'standort_id' => fn (): int => Standort::query()->value('id')
                ?? Standort::query()->create(['name' => 'Test-Standort'])->id,
            'license_plate' => strtoupper(fake()->bothify('DO-HW ###')),
            'manufacturer' => fake()->company(),
            'model' => fake()->word(),
            'fuel_type' => 'petrol',
            'initial_km' => fake()->numberBetween(1000, 80000),
            'active' => true,
            'is_new' => false,
        ];
    }

    public function electric(): static
    {
        return $this->state(fn (): array => [
            'fuel_type' => 'electric',
            'electric_range_km' => 300,
            'electric_charge_minutes' => 50,
            'vehicle_category_id' => VehicleCategory::factory()->electric(),
        ]);
    }
}
