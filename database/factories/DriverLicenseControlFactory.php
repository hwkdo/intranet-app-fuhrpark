<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Database\Factories;

use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicenseControl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverLicenseControl>
 */
class DriverLicenseControlFactory extends Factory
{
    protected $model = DriverLicenseControl::class;

    public function definition(): array
    {
        return [
            'driver_license_id' => DriverLicense::factory(),
            'inspected_by_user_id' => User::factory(),
            'note' => fake()->optional()->sentence(),
            'file_path' => null,
            'file_name' => null,
        ];
    }
}
