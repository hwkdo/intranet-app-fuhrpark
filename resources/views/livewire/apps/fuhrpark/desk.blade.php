<?php

use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Services\BookingStatusResolver;
use Hwkdo\IntranetAppFuhrpark\Services\HandoutReturnService;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, on, state, title, updated};

title('Fuhrpark - Zentrale');

state([
    'deskStandortId' => null,
    'showHandoutModal' => false,
    'showReturnModal' => false,
    'selectedBookingId' => null,
    'handoutDriverId' => null,
    'returnKmEnd' => null,
    'returnHasDamage' => false,
    'returnDamageNote' => '',
    'returnChecklist' => [
        'key' => false,
        'license' => false,
        'fuel_card' => false,
    ],
    'signatureData' => null,
    'showAllVehiclesOnRoad' => false,
]);

mount(function (): void {
    $this->deskStandortId = FuhrparkModels::vehicleStandortIdFor(Auth::user()?->standort_id);
});

$deskVehicleStandorte = computed(fn () => FuhrparkModels::vehicleStandorte());

$expectedHandouts = computed(function () {
    $resolver = app(BookingStatusResolver::class);

    $query = Booking::query()
        ->whereDate('starts_at', today())
        ->whereDoesntHave('handout')
        ->whereNotIn('purpose', [BookingPurpose::Lock, BookingPurpose::ChargeLock])
        ->with(['vehicle', 'driver'])
        ->orderBy('starts_at');

    if ($this->deskStandortId) {
        $query->whereHas('vehicle', fn ($q) => $q->where('standort_id', $this->deskStandortId));
    }

    return $query
        ->get()
        ->filter(fn (Booking $booking): bool => $resolver->isAwaitingHandoutToday($booking));
});

$expectedReturns = computed(function () {
    $resolver = app(BookingStatusResolver::class);

    $query = Booking::query()
        ->with(['vehicle', 'driver', 'handout.returnRecord'])
        ->whereHas('handout', fn ($q) => $q->whereDoesntHave('returnRecord'))
        ->whereNotIn('purpose', [BookingPurpose::Lock, BookingPurpose::ChargeLock])
        ->orderBy('ends_at');

    if ($this->deskStandortId) {
        $query->whereHas('vehicle', fn ($q) => $q->where('standort_id', $this->deskStandortId));
    }

    return $query
        ->get()
        ->filter(function (Booking $booking) use ($resolver): bool {
            if ($this->showAllVehiclesOnRoad) {
                return $resolver->isCurrentlyHandedOut($booking);
            }

            return $resolver->isAwaitingReturnToday($booking);
        });
});

$selectedBooking = computed(fn () => $this->selectedBookingId
    ? Booking::query()->with(['vehicle', 'driver', 'handout'])->find($this->selectedBookingId)
    : null);

$handoutDrivers = computed(fn () => FuhrparkModels::userQuery()
    ->where('active', true)
    ->orderBy('nachname')
    ->orderBy('vorname')
    ->get(['id', 'vorname', 'nachname']));

$handoutPredecessor = computed(function () {
    $booking = $this->selectedBooking;

    if (! $booking) {
        return null;
    }

    return app(HandoutReturnService::class)->predecessorForHandout($booking);
});

$handoutBlockedByPredecessor = computed(fn () => $this->handoutPredecessor !== null);

$handoutPredecessorStatus = computed(function () {
    $predecessor = $this->handoutPredecessor;

    if (! $predecessor) {
        return null;
    }

    return app(BookingStatusResolver::class)->resolve($predecessor);
});

$selectedHandoutDriverName = computed(function () {
    if (! $this->handoutDriverId) {
        return '';
    }

    $user = FuhrparkModels::userQuery()
        ->whereKey($this->handoutDriverId)
        ->first(['id', 'vorname', 'nachname']);

    return $user?->name ?? '';
});

updated([
    'handoutDriverId' => function (): void {
        $this->signatureData = null;
    },
]);

$openHandout = function (int $bookingId): void {
    $booking = Booking::query()->with(['vehicle', 'driver', 'handout'])->findOrFail($bookingId);
    $this->authorize('handout', $booking);

    $this->selectedBookingId = $bookingId;
    $this->handoutDriverId = $booking->driver_id;
    $this->signatureData = null;
    $this->showHandoutModal = true;
};

$openReturn = function (int $bookingId): void {
    $booking = Booking::query()->findOrFail($bookingId);
    $this->authorize('returnVehicle', $booking);
    $this->selectedBookingId = $bookingId;
    $this->returnKmEnd = $booking->km_start;
    $this->showReturnModal = true;
};

$confirmHandout = function (): void {
    $booking = $this->selectedBooking;
    if (! $booking) {
        return;
    }

    $this->authorize('handout', $booking);

    if ($this->handoutBlockedByPredecessor) {
        return;
    }

    $this->validate(
        [
            'handoutDriverId' => 'required|integer|exists:users,id',
            'signatureData' => 'required|string',
        ],
        [
            'handoutDriverId.required' => 'Bitte wählen Sie einen Fahrer aus.',
            'signatureData.required' => 'Bitte erfassen Sie zuerst eine Unterschrift.',
        ],
    );

    app(HandoutReturnService::class)->handout(
        $booking,
        Auth::user(),
        (int) $this->handoutDriverId,
        ['data' => $this->signatureData],
    );

    $this->showHandoutModal = false;
    $this->signatureData = null;
};

$confirmReturn = function (): void {
    $booking = $this->selectedBooking;
    $handout = $booking?->handout;
    if (! $handout) {
        return;
    }

    $this->validate(['returnKmEnd' => 'required|integer|min:0']);

    app(HandoutReturnService::class)->returnVehicle(
        $handout,
        Auth::user(),
        (int) $booking->driver_id,
        (int) $this->returnKmEnd,
        $this->returnChecklist,
        $this->returnHasDamage,
        $this->returnDamageNote ?: null,
    );

    $this->showReturnModal = false;
};

on(['signature-confirmed' => function (string $img_src, string $base64, array $checkboxes) {
    $this->signatureData = $base64;
}]);

?>

<x-intranet-app-fuhrpark::fuhrpark-layout heading="Zentrale" subheading="Ausgabe und Rückgabe">
    <flux:card class="mb-6">
        <flux:field class="max-w-md">
            <flux:label>Fahrzeugstandort</flux:label>
            <flux:select
                wire:model.live="deskStandortId"
                variant="listbox"
                searchable
                placeholder="Fahrzeugstandort wählen…"
            >
                @foreach ($this->deskVehicleStandorte as $standort)
                    <flux:select.option value="{{ $standort->id }}">{{ $standort->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
    </flux:card>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card>
            <flux:heading size="lg">Erwartete Abholungen</flux:heading>
            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Fahrzeug</flux:table.column>
                    <flux:table.column>Fahrer</flux:table.column>
                    <flux:table.column>Zeit</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->expectedHandouts as $booking)
                        <flux:table.row>
                            <flux:table.cell>{{ $booking->vehicle->license_plate }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->driver->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->starts_at->format('H:i') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="sm" wire:click="openHandout({{ $booking->id }})">Ausgeben</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:card>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <flux:heading size="lg">
                    {{ $showAllVehiclesOnRoad ? 'Fahrzeuge unterwegs' : 'Erwartete Rückgaben' }}
                </flux:heading>

                <flux:field class="shrink-0">
                    <flux:label>Anzeigemodus</flux:label>
                    <div class="flex items-center gap-3">
                        <flux:text class="text-sm {{ ! $showAllVehiclesOnRoad ? 'font-medium text-zinc-900 dark:text-white' : 'text-zinc-500' }}">
                            Nur Erwartete Fahrzeuge
                        </flux:text>
                        <flux:switch wire:model.live="showAllVehiclesOnRoad" />
                        <flux:text class="text-sm {{ $showAllVehiclesOnRoad ? 'font-medium text-zinc-900 dark:text-white' : 'text-zinc-500' }}">
                            Alle Fahrzeuge
                        </flux:text>
                    </div>
                </flux:field>
            </div>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Fahrzeug</flux:table.column>
                    <flux:table.column>Fahrer</flux:table.column>
                    @if ($showAllVehiclesOnRoad)
                        <flux:table.column>Rückgabe erwartet</flux:table.column>
                    @endif
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->expectedReturns as $booking)
                        <flux:table.row>
                            <flux:table.cell>{{ $booking->vehicle->license_plate }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->driver->name ?? '-' }}</flux:table.cell>
                            @if ($showAllVehiclesOnRoad)
                                <flux:table.cell>{{ $booking->ends_at->format('d.m.Y H:i') }}</flux:table.cell>
                            @endif
                            <flux:table.cell>
                                <flux:button size="sm" wire:click="openReturn({{ $booking->id }})">Rückgabe</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="{{ $showAllVehiclesOnRoad ? 4 : 3 }}" class="text-zinc-500">
                                {{ $showAllVehiclesOnRoad ? 'Keine Fahrzeuge unterwegs.' : 'Keine Rückgaben für heute erwartet.' }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    <flux:modal wire:model="showHandoutModal" class="md:w-xl">
        <flux:heading size="lg">Fahrzeug ausgeben</flux:heading>
        @if($showHandoutModal && $this->selectedBooking)
            <flux:text class="mt-2">{{ $this->selectedBooking->vehicle->license_plate }}</flux:text>

            @if ($this->handoutBlockedByPredecessor && $this->handoutPredecessor)
                <flux:callout variant="warning" icon="exclamation-triangle" class="mt-4">
                    Diese Buchung kann nicht ausgegeben werden, da die Vorgängerbuchung noch nicht zurückgegeben wurde.
                </flux:callout>

                <div class="mt-4 space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">Vorgängerbuchung</flux:heading>

                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-zinc-500">Kennzeichen</dt>
                            <dd class="font-medium">{{ $this->handoutPredecessor->vehicle->license_plate }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Fahrer</dt>
                            <dd class="font-medium">{{ $this->handoutPredecessor->driver->name ?? '–' }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Zeitraum</dt>
                            <dd class="font-medium">
                                {{ $this->handoutPredecessor->starts_at->format('d.m.Y H:i') }}
                                –
                                {{ $this->handoutPredecessor->ends_at->format('d.m.Y H:i') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Status</dt>
                            <dd class="font-medium">{{ $this->handoutPredecessorStatus?->label() ?? '–' }}</dd>
                        </div>
                    </dl>
                </div>
            @else
                <flux:field class="mt-4">
                    <flux:label>Fahrer</flux:label>
                    <flux:select
                        wire:model.live="handoutDriverId"
                        variant="listbox"
                        searchable
                        placeholder="Fahrer auswählen…"
                    >
                        @foreach ($this->handoutDrivers as $user)
                            <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <div class="mt-4" wire:key="handout-signopad-{{ $handoutDriverId }}">
                    <livewire:signopad.signpad
                        :fields="[]"
                        text-oben="Fahrzeugübergabe {{ $this->selectedBooking->vehicle->license_plate }}"
                        text-unten="{{ $this->selectedHandoutDriverName }}"
                        :key="'signpad-'.$handoutDriverId"
                    />
                </div>

                @if ($signatureData)
                    <flux:callout variant="success" icon="check-circle" class="mt-4">
                        Unterschrift erfasst. Sie können die Ausgabe jetzt bestätigen.
                    </flux:callout>
                @else
                    <flux:text class="mt-4 text-zinc-500">
                        Bitte zuerst die Unterschrift am Signopad erfassen.
                    </flux:text>
                @endif

                <flux:button
                    class="mt-4"
                    variant="primary"
                    wire:click="confirmHandout"
                    :disabled="! $signatureData || ! $handoutDriverId"
                >
                    Ausgabe bestätigen
                </flux:button>
            @endif
        @endif
    </flux:modal>

    <flux:modal wire:model="showReturnModal" class="md:w-lg">
        <flux:heading size="lg">Fahrzeug zurücknehmen</flux:heading>
        @if ($showReturnModal && $this->selectedBooking)
        <div class="mt-4 space-y-4">
            <flux:input wire:model="returnKmEnd" type="number" label="KM Ende" />
            <flux:checkbox wire:model="returnChecklist.key" label="Schlüssel" />
            <flux:checkbox wire:model="returnChecklist.license" label="Führerschein" />
            <flux:checkbox wire:model="returnChecklist.fuel_card" label="Tankkarte" />
            <flux:checkbox wire:model="returnHasDamage" label="Schaden" />
            @if($returnHasDamage)
                <flux:textarea wire:model="returnDamageNote" label="Schadensbeschreibung" />
            @endif
            <flux:button variant="primary" wire:click="confirmReturn">Rückgabe bestätigen</flux:button>
        </div>
        @endif
    </flux:modal>
</x-intranet-app-fuhrpark::fuhrpark-layout>
