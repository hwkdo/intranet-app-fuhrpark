<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Database\Factories;

use App\Models\User;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandReason;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingDemandSource;
use Hwkdo\IntranetAppFuhrpark\Models\BookingDemandEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingDemandEvent>
 */
class BookingDemandEventFactory extends Factory
{
    protected $model = BookingDemandEvent::class;

    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 5))->setTime(8, 0);
        $end = $start->copy()->addHours(4);

        return [
            'user_id' => User::factory(),
            'standort_id' => null,
            'vehicle_category_id' => null,
            'vehicle_id' => null,
            'driver_id' => null,
            'starts_at' => $start,
            'ends_at' => $end,
            'reason' => BookingDemandReason::NoVehicleInCategory,
            'source' => BookingDemandSource::Create,
            'had_alternative_category' => false,
        ];
    }
}
