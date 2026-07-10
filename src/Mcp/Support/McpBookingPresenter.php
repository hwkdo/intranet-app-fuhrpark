<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Mcp\Support;

use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\LogbookEntry;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class McpBookingPresenter
{
    public function __construct(
        private readonly BookingStatusResolver $statusResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(Booking $booking, ?Authenticatable $viewer = null): array
    {
        $status = $this->statusResolver->resolve($booking);
        $viewerId = $viewer?->getAuthIdentifier();

        $roles = [];
        if ($viewerId !== null) {
            if ((int) $booking->driver_id === (int) $viewerId) {
                $roles[] = 'fahrer';
            }
            if ((int) $booking->user_id === (int) $viewerId) {
                $roles[] = 'bucher';
            }
        }

        return [
            'id' => $booking->id,
            'status' => $status->value,
            'status_label' => $status->label(),
            'purpose' => $booking->purpose->value,
            'purpose_label' => $booking->purpose->label(),
            'description' => $booking->description,
            'starts_at' => $booking->starts_at->toIso8601String(),
            'ends_at' => $booking->ends_at->toIso8601String(),
            'is_commute' => (bool) $booking->is_commute,
            'vehicle' => $this->formatVehicle($booking),
            'driver' => $this->formatUser($booking->driver),
            'booker' => $this->formatUser($booking->booker),
            'meine_rollen' => $roles,
            'has_logbook' => $booking->logbookEntry !== null,
            'km_start' => $booking->km_start,
            'km_end' => $booking->km_end,
            'url' => route('apps.fuhrpark.meine'),
            'url_markdown' => sprintf('[Buchung #%d](%s)', $booking->id, route('apps.fuhrpark.meine')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Booking $booking, ?Authenticatable $viewer = null): array
    {
        $summary = $this->summarize($booking, $viewer);
        $canViewLogbook = $viewer !== null && Gate::forUser($viewer)->check('viewLogbook', $booking);

        return array_merge($summary, [
            'electric_route_km' => $booking->electric_route_km,
            'sync_to_calendar' => (bool) $booking->sync_to_calendar,
            'can_cancel' => $viewer !== null && Gate::forUser($viewer)->check('cancel', $booking),
            'can_update' => $viewer !== null && Gate::forUser($viewer)->check('update', $booking),
            'handout' => $booking->handout ? [
                'id' => $booking->handout->id,
                'handed_out_at' => $booking->handout->created_at?->toIso8601String(),
                'processed_by' => $this->formatUser($booking->handout->processedBy),
            ] : null,
            'return' => $booking->returnRecord ? [
                'id' => $booking->returnRecord->id,
                'returned_at' => $booking->returnRecord->created_at?->toIso8601String(),
                'km_end' => $booking->returnRecord->km_end,
                'has_damage' => (bool) $booking->returnRecord->has_damage,
                'damage_note' => $booking->returnRecord->damage_note,
                'processed_by' => $this->formatUser($booking->returnRecord->processedBy),
            ] : null,
            'logbook' => $canViewLogbook ? $this->formatLogbook($booking->logbookEntry) : null,
            'logbook_visible' => $canViewLogbook,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatVehicle(Booking $booking): ?array
    {
        $vehicle = $booking->vehicle;
        if ($vehicle === null) {
            return null;
        }

        return [
            'id' => $vehicle->id,
            'license_plate' => $vehicle->license_plate,
            'manufacturer' => $vehicle->manufacturer,
            'model' => $vehicle->model,
            'category' => $vehicle->category?->name,
            'category_id' => $vehicle->vehicle_category_id,
            'is_electric' => $vehicle->isElectric(),
            'fuel_type' => $vehicle->fuel_type,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatUser(?Model $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $name = trim(((string) ($user->vorname ?? '')).' '.((string) ($user->nachname ?? '')));

        return [
            'id' => $user->getKey(),
            'name' => $name !== '' ? $name : (string) ($user->username ?? ''),
            'username' => (string) ($user->username ?? ''),
            'email' => (string) ($user->email ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatLogbook(?LogbookEntry $entry): ?array
    {
        if ($entry === null) {
            return null;
        }

        return [
            'id' => $entry->id,
            'route' => $entry->route,
            'km_commute' => $entry->km_commute,
            'km_project' => $entry->km_project,
            'km_total' => (int) $entry->km_commute + (int) $entry->km_project,
            'fueled' => (bool) $entry->fueled,
            'cleaned' => (bool) $entry->cleaned,
            'note' => $entry->note,
            'project' => $entry->project ? [
                'id' => $entry->project->id,
                'name' => $entry->project->name,
            ] : null,
            'recorded_by' => $this->formatUser($entry->user),
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }
}
