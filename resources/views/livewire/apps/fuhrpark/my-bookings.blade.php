<?php

use Hwkdo\IntranetAppFuhrpark\Enums\BookingStatus;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Project;
use Hwkdo\IntranetAppFuhrpark\Services\BookingService;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Hwkdo\IntranetAppFuhrpark\Services\LogbookService;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, state, title};

title('Fuhrpark - Meine Buchungen');

state([
    'filter' => 'open',
    'showLogbookModal' => false,
    'showViewLogbookModal' => false,
    'showCancelModal' => false,
    'selectedBookingId' => null,
    'cancelReason' => '',
    'logbookRoute' => '',
    'logbookKmCommute' => 0,
    'logbookKmProject' => 0,
    'logbookProjectId' => null,
    'logbookFueled' => false,
    'logbookCleaned' => false,
]);

$bookings = computed(function () {
    $query = Booking::query()
        ->where('driver_id', Auth::id())
        ->with(['vehicle.category', 'handout.returnRecord', 'logbookEntry'])
        ->orderByDesc('starts_at');

    if ($this->filter === 'open') {
        return $query->get()->filter(function (Booking $booking): bool {
            $status = app(BookingStatusResolver::class)->resolve($booking);

            return ! in_array($status, [BookingStatus::Completed], true);
        })->values();
    }

    return $query->limit(100)->get();
});

$projects = computed(fn () => Project::query()->where('active', true)->orderBy('name')->get());

$selectedBooking = computed(fn () => $this->selectedBookingId
    ? Booking::query()->with(['vehicle', 'handout.returnRecord', 'logbookEntry'])->find($this->selectedBookingId)
    : null);

$cancelRequiresReason = computed(function () {
    $booking = $this->selectedBooking;

    if (! $booking) {
        return false;
    }

    return app(BookingStatusResolver::class)->requiresCancellationReason($booking);
});

$selectedLogbookEntry = computed(fn () => $this->selectedBooking?->logbookEntry);

$openLogbook = function (int $bookingId): void {
    $this->selectedBookingId = $bookingId;
    $this->logbookRoute = '';
    $this->logbookKmCommute = 0;
    $this->logbookKmProject = 0;
    $this->showLogbookModal = true;
};

$openViewLogbook = function (int $bookingId): void {
    $booking = Booking::query()->with(['logbookEntry.project', 'vehicle'])->findOrFail($bookingId);
    $this->authorize('viewLogbook', $booking);
    $this->selectedBookingId = $bookingId;
    $this->showViewLogbookModal = true;
};

$saveLogbook = function (): void {
    $this->validate([
        'logbookRoute' => 'required|string|max:500',
        'logbookKmCommute' => 'required|integer|min:0',
        'logbookKmProject' => 'required|integer|min:0',
    ]);

    app(LogbookService::class)->create(Auth::user(), [
        'booking_id' => $this->selectedBookingId,
        'route' => $this->logbookRoute,
        'km_commute' => $this->logbookKmCommute,
        'km_project' => $this->logbookKmProject,
        'project_id' => $this->logbookProjectId,
        'fueled' => $this->logbookFueled,
        'cleaned' => $this->logbookCleaned,
    ]);

    $this->showLogbookModal = false;
};

$openCancel = function (int $bookingId): void {
    $booking = Booking::query()
        ->with(['handout.returnRecord', 'logbookEntry'])
        ->where('driver_id', Auth::id())
        ->findOrFail($bookingId);

    if (! app(BookingStatusResolver::class)->canBeCancelledByDriver($booking)) {
        abort(403);
    }

    $this->selectedBookingId = $bookingId;
    $this->cancelReason = '';
    $this->showCancelModal = true;
};

$confirmCancel = function (): void {
    $booking = $this->selectedBooking;
    if (! $booking) {
        return;
    }

    if (! app(BookingStatusResolver::class)->canBeCancelledByDriver($booking)) {
        abort(403);
    }

    if (app(BookingStatusResolver::class)->requiresCancellationReason($booking)) {
        $this->validate(
            ['cancelReason' => 'required|string|min:3'],
            ['cancelReason.required' => 'Bitte geben Sie eine Begründung an.'],
        );
    }

    app(BookingService::class)->cancel($booking, $this->cancelReason ?: null, Auth::user());
    $this->showCancelModal = false;
    $this->cancelReason = '';
};

?>

<x-intranet-app-fuhrpark::fuhrpark-layout heading="Meine Buchungen" subheading="Übersicht Ihrer Fahrzeugbuchungen">
    <div class="mb-4 flex gap-2">
        <flux:button wire:click="$set('filter', 'open')" :variant="$filter === 'open' ? 'primary' : 'ghost'">Offen</flux:button>
        <flux:button wire:click="$set('filter', 'all')" :variant="$filter === 'all' ? 'primary' : 'ghost'">Alle</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Fahrzeug</flux:table.column>
            <flux:table.column>Zeitraum</flux:table.column>
            <flux:table.column>Zweck</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->bookings as $booking)
                @php $status = app(BookingStatusResolver::class)->resolve($booking); @endphp
                <flux:table.row>
                    <flux:table.cell>{{ $booking->vehicle->license_plate }}</flux:table.cell>
                    <flux:table.cell>{{ $booking->starts_at->format('d.m.Y H:i') }} – {{ $booking->ends_at->format('d.m.Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $booking->description }}</flux:table.cell>
                    <flux:table.cell>{{ $status->label() }}</flux:table.cell>
                    <flux:table.cell class="flex flex-wrap gap-2">
                        @if($status === BookingStatus::Returned)
                            <flux:button size="sm" wire:click="openLogbook({{ $booking->id }})">Fahrtenbuch erfassen</flux:button>
                        @endif
                        @if($booking->logbookEntry)
                            @can('viewLogbook', $booking)
                                <flux:button size="sm" variant="ghost" wire:click="openViewLogbook({{ $booking->id }})">Fahrtenbuch anzeigen</flux:button>
                            @endcan
                        @endif
                        @if(app(BookingStatusResolver::class)->canBeCancelledByDriver($booking))
                            <flux:button size="sm" variant="danger" wire:click="openCancel({{ $booking->id }})">Löschen</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showLogbookModal" class="md:w-lg">
        <flux:heading size="lg">Fahrtenbucheintrag</flux:heading>
        <div class="mt-4 space-y-4">
            <flux:input wire:model="logbookRoute" label="Route" />
            <flux:input wire:model="logbookKmCommute" type="number" label="KM Arbeitsfahrt" />
            <flux:input wire:model="logbookKmProject" type="number" label="KM Projektfahrt" />
            @if($logbookKmProject > 0)
                <flux:select wire:model="logbookProjectId" label="Projekt">
                    @foreach($this->projects as $project)
                        <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:checkbox wire:model="logbookFueled" label="Getankt" />
            <flux:checkbox wire:model="logbookCleaned" label="Gereinigt" />
            <flux:button variant="primary" wire:click="saveLogbook">Speichern</flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="showViewLogbookModal" class="md:w-lg">
        <flux:heading size="lg">Fahrtenbucheintrag</flux:heading>

        @if ($this->selectedLogbookEntry)
            <div class="mt-4 space-y-3">
                <flux:text>
                    <span class="font-medium">Fahrzeug:</span>
                    {{ $this->selectedBooking?->vehicle->license_plate }}
                </flux:text>
                <flux:text>
                    <span class="font-medium">Zeitraum:</span>
                    {{ $this->selectedBooking?->starts_at->format('d.m.Y H:i') }}
                    –
                    {{ $this->selectedBooking?->ends_at->format('d.m.Y H:i') }}
                </flux:text>
                <flux:text>
                    <span class="font-medium">Route:</span>
                    {{ $this->selectedLogbookEntry->route }}
                </flux:text>
                <flux:text>
                    <span class="font-medium">KM Arbeitsfahrt:</span>
                    {{ $this->selectedLogbookEntry->km_commute }}
                </flux:text>
                <flux:text>
                    <span class="font-medium">KM Projektfahrt:</span>
                    {{ $this->selectedLogbookEntry->km_project }}
                </flux:text>
                @if ($this->selectedLogbookEntry->project)
                    <flux:text>
                        <span class="font-medium">Projekt:</span>
                        {{ $this->selectedLogbookEntry->project->name }}
                    </flux:text>
                @endif
                <flux:text>
                    <span class="font-medium">KM Stand:</span>
                    {{ $this->selectedBooking?->km_start ?? '–' }} → {{ $this->selectedBooking?->km_end ?? '–' }}
                </flux:text>
                <flux:text>
                    <span class="font-medium">Getankt:</span> {{ $this->selectedLogbookEntry->fueled ? 'Ja' : 'Nein' }}
                </flux:text>
                <flux:text>
                    <span class="font-medium">Gereinigt:</span> {{ $this->selectedLogbookEntry->cleaned ? 'Ja' : 'Nein' }}
                </flux:text>
                @if ($this->selectedLogbookEntry->note)
                    <flux:text>
                        <span class="font-medium">Notiz:</span>
                        {{ $this->selectedLogbookEntry->note }}
                    </flux:text>
                @endif
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="showCancelModal" class="md:w-lg">
        <flux:heading size="lg">Buchung löschen</flux:heading>

        @if ($this->cancelRequiresReason)
            <flux:text class="mt-2 text-zinc-500">
                Die Buchung ist überfällig. Bitte geben Sie eine Begründung an. Die Fuhrpark-Administration wird informiert.
            </flux:text>
            <flux:textarea wire:model="cancelReason" label="Begründung" class="mt-4" />
        @else
            <flux:text class="mt-4">
                Möchten Sie die Buchung wirklich löschen?
            </flux:text>
        @endif

        <flux:button class="mt-4" variant="danger" wire:click="confirmCancel">Löschen bestätigen</flux:button>
    </flux:modal>
</x-intranet-app-fuhrpark::fuhrpark-layout>
