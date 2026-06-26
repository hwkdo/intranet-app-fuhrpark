<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Database\Factories;

use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverLicense>
 */
class DriverLicenseFactory extends Factory
{
    protected $model = DriverLicense::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'valid_until' => now()->addYear(),
            'restricted_until' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'valid_until' => now()->subDay(),
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (): array => [
            'valid_until' => now()->addDays(14),
        ]);
    }

    public function restrictedExpired(): static
    {
        return $this->state(fn (): array => [
            'restricted_until' => now()->subDay(),
        ]);
    }
}
