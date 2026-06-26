<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Tasks;

use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingStatus;
use Hwkdo\IntranetAppFuhrpark\IntranetAppFuhrpark;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class NoShowBookingTaskProvider implements TaskProviderInterface
{
    public function __construct(
        private readonly BookingStatusResolver $statusResolver,
    ) {}

    public function getLabel(): string
    {
        return 'Nicht angetretene Fahrten';
    }

    public function getTasksForUser(Authenticatable $user): Collection
    {
        return Booking::query()
            ->where('driver_id', $user->getAuthIdentifier())
            ->with(['vehicle', 'handout.returnRecord', 'logbookEntry'])
            ->get()
            ->filter(fn (Booking $b): bool => $this->statusResolver->resolve($b) === BookingStatus::NoShow)
            ->map(fn (Booking $b): TaskItem => new TaskItem(
                title: 'Nicht angetreten: '.$b->vehicle->license_plate,
                url: route('apps.fuhrpark.meine'),
                appIdentifier: IntranetAppFuhrpark::identifier(),
                appName: IntranetAppFuhrpark::app_name(),
                appIcon: IntranetAppFuhrpark::app_icon(),
                description: 'Buchung mit Begründung löschen',
                priority: 60,
            ))
            ->values();
    }
}
