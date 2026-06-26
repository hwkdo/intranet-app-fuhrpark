<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Services;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppFuhrpark\Data\BookingStoreData;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Mail\ProjectTripChangedMail;
use Hwkdo\IntranetAppFuhrpark\Mail\ProjectTripRecordedMail;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\IntranetAppFuhrparkSettings;
use Hwkdo\IntranetAppFuhrpark\Models\LogbookEntry;
use Hwkdo\IntranetAppFuhrpark\Models\Project;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class LogbookService
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Authenticatable $user, array $data, bool $asAdmin = false): LogbookEntry
    {
        $entry = LogbookEntry::query()->create([
            'booking_id' => $data['booking_id'],
            'user_id' => $asAdmin ? $data['user_id'] : $user->getAuthIdentifier(),
            'project_id' => ($data['km_project'] ?? 0) > 0 ? $data['project_id'] : null,
            'route' => $data['route'],
            'km_commute' => $data['km_commute'] ?? 0,
            'km_project' => $data['km_project'] ?? 0,
            'fueled' => (bool) ($data['fueled'] ?? false),
            'cleaned' => (bool) ($data['cleaned'] ?? false),
            'note' => $data['note'] ?? null,
        ]);

        $booking = $entry->booking;
        if ($booking->vehicle->is_new) {
            $booking->vehicle->update(['is_new' => false]);
        }

        if ($entry->km_project > 0) {
            $this->notifyProjectRecipients($entry, false);
        }

        return $entry;
    }

    public function update(LogbookEntry $entry, array $data): LogbookEntry
    {
        $oldKmProject = $entry->km_project;
        $entry->update($data);

        if ($entry->km_project !== $oldKmProject && $entry->km_project > 0) {
            $this->notifyProjectRecipients($entry, true, $oldKmProject);
        }

        return $entry->fresh();
    }

    public function createWorkshopTrip(
        Vehicle $vehicle,
        Authenticatable $admin,
        int $driverId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Booking {
        return $this->bookingService->create(
            new BookingStoreData(
                vehicleId: $vehicle->id,
                driverId: $driverId,
                description: 'Werkstattfahrt',
                startsAt: $start,
                endsAt: $end,
                isCommute: false,
            ),
            $admin,
            BookingPurpose::Workshop,
        );
    }

    public function entriesForVehicle(Vehicle $vehicle): \Illuminate\Support\Collection
    {
        return LogbookEntry::query()
            ->whereHas('booking', fn ($query) => $query->where('vehicle_id', $vehicle->id))
            ->with(['booking', 'user', 'project'])
            ->get()
            ->sortBy(fn (LogbookEntry $entry) => $entry->booking->starts_at)
            ->values();
    }

    private function notifyProjectRecipients(LogbookEntry $entry, bool $changed, int $oldKm = 0): void
    {
        $roleName = IntranetAppFuhrparkSettings::current()->settings->projectNotifyRole;
        $role = Role::findByName($roleName, 'web');
        $project = $entry->project_id ? Project::query()->find($entry->project_id) : null;

        foreach ($role->users as $recipient) {
            $mailable = $changed
                ? new ProjectTripChangedMail($entry, $project, $oldKm)
                : new ProjectTripRecordedMail($entry, $project);

            Mail::to($recipient->email)->queue($mailable);
        }
    }
}
