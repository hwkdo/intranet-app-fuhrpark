<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Database\Factories;

use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 5))->setTime(8, 0);
        $end = $start->copy()->addHours(4);

        return [
            'vehicle_id' => Vehicle::factory(),
            'user_id' => User::factory(),
            'driver_id' => User::factory(),
            'purpose' => BookingPurpose::Normal,
            'description' => fake()->sentence(),
            'is_commute' => false,
            'starts_at' => $start,
            'ends_at' => $end,
        ];
    }
}
