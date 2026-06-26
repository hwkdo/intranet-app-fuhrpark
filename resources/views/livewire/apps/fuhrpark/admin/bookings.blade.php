<?php

use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use function Livewire\Volt\{computed, state};

state(['cancelReason' => '', 'selectedBookingId' => null, 'showCancelModal' => false]);

$bookings = computed(fn () => Booking::query()
    ->with(['vehicle', 'driver', 'handout.returnRecord', 'logbookEntry'])
    ->orderByDesc('starts_at')
    ->limit(200)
    ->get());

$openCancel = function (int $id): void {
    $this->selectedBookingId = $id;
    $this->cancelReason = '';
    $this->showCancelModal = true;
};

$confirmCancel = function (): void {
    $booking = Booking::query()->findOrFail($this->selectedBookingId);
    $this->authorize('delete', $booking);
    app(BookingService::class)->cancel($booking, $this->cancelReason ?: null, auth()->user(), force: true);
    $this->showCancelModal = false;
};

?>

<div>
    <flux:table>
        <flux:table.columns>
            <flux:table.column>ID</flux:table.column>
            <flux:table.column>Fahrzeug</flux:table.column>
            <flux:table.column>Fahrer</flux:table.column>
            <flux:table.column>Zeitraum</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->bookings as $booking)
                @php $status = app(BookingStatusResolver::class)->resolve($booking); @endphp
                <flux:table.row>
                    <flux:table.cell>{{ $booking->id }}</flux:table.cell>
                    <flux:table.cell>{{ $booking->vehicle->license_plate }}</flux:table.cell>
                    <flux:table.cell>{{ $booking->driver->name ?? '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $booking->starts_at->format('d.m.Y H:i') }} – {{ $booking->ends_at->format('d.m.Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $status->value }}</flux:table.cell>
                    <flux:table.cell>
                        @can('delete', $booking)
                            <flux:button size="sm" variant="danger" wire:click="openCancel({{ $booking->id }})">Löschen</flux:button>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showCancelModal" class="md:w-lg">
        <flux:heading size="lg">Buchung löschen</flux:heading>
        <flux:textarea wire:model="cancelReason" label="Begründung (optional)" class="mt-4" />
        <flux:button class="mt-4" variant="danger" wire:click="confirmCancel">Löschen</flux:button>
    </flux:modal>
</div>
