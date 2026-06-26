<?php

use Carbon\Carbon;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingStatus;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use function Livewire\Volt\{computed, title};

title('Fuhrpark - Mein Team');

$teamMemberIds = computed(function () {
    $user = auth()->user();
    if (! ($user->ist_vorgesetzter ?? false)) {
        return collect();
    }

    $untergebene = method_exists($user, 'getUntergebene') ? $user->getUntergebene(true) : false;

    if ($untergebene === false) {
        return collect();
    }

    return $untergebene->pluck('id');
});

$bookings = computed(function () {
    $ids = $this->teamMemberIds;
    if ($ids->isEmpty()) {
        return collect();
    }

    return Booking::query()
        ->whereIn('driver_id', $ids)
        ->where('starts_at', '>=', now()->subMonth())
        ->with(['vehicle', 'driver'])
        ->orderByDesc('starts_at')
        ->limit(200)
        ->get()
        ->map(function (Booking $booking): array {
            return [
                'booking' => $booking,
                'status' => app(BookingStatusResolver::class)->resolve($booking),
            ];
        });
});

?>

<x-intranet-app-fuhrpark::fuhrpark-layout heading="Mein Team" subheading="Buchungen Ihrer Mitarbeitenden">
    @if($this->teamMemberIds->isEmpty())
        <flux:callout>Keine Teammitglieder gefunden oder keine Vorgesetzten-Berechtigung.</flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Fahrer</flux:table.column>
                <flux:table.column>Fahrzeug</flux:table.column>
                <flux:table.column>Zeitraum</flux:table.column>
                <flux:table.column>Status</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($this->bookings as $row)
                    <flux:table.row>
                        <flux:table.cell>{{ $row['booking']->driver->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $row['booking']->vehicle->license_plate }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $row['booking']->starts_at->format('d.m.Y H:i') }} –
                            {{ $row['booking']->ends_at->format('d.m.Y H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $row['status']->value }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</x-intranet-app-fuhrpark::fuhrpark-layout>
