<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Database\Factories;

use Hwkdo\IntranetAppFuhrpark\Models\StandortSetting;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StandortSetting>
 */
class StandortSettingFactory extends Factory
{
    protected $model = StandortSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'standort_id' => 1,
            'is_vehicle_standort' => false,
            'vehicle_standort_id' => null,
        ];
    }

    public function vehicleStandort(): static
    {
        return $this->state(fn (): array => [
            'is_vehicle_standort' => true,
            'vehicle_standort_id' => null,
        ]);
    }
}
