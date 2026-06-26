<?php

use Flux\Flux;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use Hwkdo\IntranetAppFuhrpark\Services\VehicleAdminService;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use function Livewire\Volt\{computed, on, state};

state([
    'showModal' => false,
    'editingId' => null,
    'licensePlate' => '',
    'categoryId' => null,
    'standortId' => null,
    'manufacturer' => '',
    'model' => '',
    'fuelType' => 'petrol',
    'electricRangeKm' => null,
    'electricChargeMinutes' => null,
    'active' => true,
    'showStatusModal' => false,
    'statusVehicleId' => null,
    'inactiveReason' => '',
    'lockStart' => '',
    'lockEnd' => '',
    'lockReason' => '',
    'lockStep' => 1,
    'deactivateStep' => 1,
    'availabilityStep' => 1,
    'workshopDriverId' => null,
    'workshopStart' => '',
    'workshopEnd' => '',
    'availableFrom' => '',
    'availableUntil' => '',
]);

$vehicles = computed(fn () => Vehicle::query()->with(['category', 'standort'])->orderBy('license_plate')->get());
$categories = computed(fn () => VehicleCategory::query()->orderBy('name')->get());
$standorte = computed(fn () => FuhrparkModels::vehicleStandorte());

$workshopDrivers = computed(fn () => FuhrparkModels::userQuery()
    ->orderBy('nachname')
    ->orderBy('vorname')
    ->get());

$statusVehicle = computed(function () {
    if (! $this->statusVehicleId) {
        return null;
    }

    return Vehicle::query()->find($this->statusVehicleId);
});

$activeLockBookings = computed(function () {
    $vehicle = $this->statusVehicle;

    if (! $vehicle) {
        return collect();
    }

    return app(VehicleAdminService::class)->activeLockBookings($vehicle);
});

$availabilityConflictsRemaining = computed(function () {
    if ($this->availabilityStep !== 2 || ! $this->statusVehicleId) {
        return collect();
    }

    $vehicle = Vehicle::query()->find($this->statusVehicleId);

    if (! $vehicle) {
        return collect();
    }

    $from = $this->availableFrom !== '' ? \Carbon\Carbon::parse($this->availableFrom) : null;
    $until = $this->availableUntil !== '' ? \Carbon\Carbon::parse($this->availableUntil) : null;

    return app(VehicleAdminService::class)->conflictingBookingsForAvailability($vehicle, $from, $until);
});

$canConfirmAvailability = computed(fn () => $this->availabilityStep === 2 && $this->availabilityConflictsRemaining->isEmpty());

$deactivateConflictsRemaining = computed(function () {
    if ($this->deactivateStep !== 2 || ! $this->statusVehicleId) {
        return collect();
    }

    $vehicle = Vehicle::query()->find($this->statusVehicleId);

    if (! $vehicle) {
        return collect();
    }

    return app(VehicleAdminService::class)->conflictingBookingsForDeactivation($vehicle);
});

$canConfirmDeactivate = computed(fn () => $this->deactivateStep === 2 && $this->deactivateConflictsRemaining->isEmpty());

$lockConflictsRemaining = computed(function () {
    if ($this->lockStep !== 2 || ! $this->statusVehicleId || $this->lockStart === '' || $this->lockEnd === '') {
        return collect();
    }

    $vehicle = Vehicle::query()->find($this->statusVehicleId);

    if (! $vehicle) {
        return collect();
    }

    return app(VehicleAdminService::class)->conflictingBookingsForLock(
        $vehicle,
        \Carbon\Carbon::parse($this->lockStart),
        \Carbon\Carbon::parse($this->lockEnd),
    );
});

$canConfirmLock = computed(fn () => $this->lockStep === 2 && $this->lockConflictsRemaining->isEmpty());

on(['lock-conflict-resolved' => function () {
    unset(
        $this->lockConflictsRemaining,
        $this->canConfirmLock,
        $this->deactivateConflictsRemaining,
        $this->canConfirmDeactivate,
        $this->availabilityConflictsRemaining,
        $this->canConfirmAvailability,
        $this->activeLockBookings,
    );
}]);

$openCreate = function (): void {
    $this->reset([
        'editingId',
        'licensePlate',
        'manufacturer',
        'model',
        'fuelType',
        'electricRangeKm',
        'electricChargeMinutes',
        'active',
    ]);
    $this->categoryId = $this->categories->first()?->id;
    $this->standortId = $this->standorte->first()?->id;
    $this->fuelType = 'petrol';
    $this->active = true;
    $this->showModal = true;
};

$openEdit = function (int $id): void {
    $vehicle = Vehicle::query()->findOrFail($id);
    $this->editingId = $id;
    $this->licensePlate = $vehicle->license_plate;
    $this->categoryId = $vehicle->vehicle_category_id;
    $this->standortId = $vehicle->standort_id;
    $this->manufacturer = $vehicle->manufacturer ?? '';
    $this->model = $vehicle->model ?? '';
    $this->fuelType = $vehicle->fuel_type;
    $this->electricRangeKm = $vehicle->electric_range_km;
    $this->electricChargeMinutes = $vehicle->electric_charge_minutes;
    $this->active = $vehicle->active;
    $this->showModal = true;
};

$save = function (): void {
    $rules = [
        'licensePlate' => 'required|string|max:20',
        'categoryId' => 'required|integer',
        'standortId' => 'required|integer',
        'fuelType' => 'required|in:petrol,diesel,electric',
    ];

    if ($this->fuelType === 'electric') {
        $rules['electricRangeKm'] = 'required|integer|min:1';
        $rules['electricChargeMinutes'] = 'required|integer|min:1';
    }

    $this->validate($rules);

    Vehicle::query()->updateOrCreate(
        ['id' => $this->editingId],
        [
            'license_plate' => $this->licensePlate,
            'vehicle_category_id' => $this->categoryId,
            'standort_id' => $this->standortId,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'fuel_type' => $this->fuelType,
            'electric_range_km' => $this->fuelType === 'electric' ? (int) $this->electricRangeKm : null,
            'electric_charge_minutes' => $this->fuelType === 'electric' ? (int) $this->electricChargeMinutes : null,
            'active' => (bool) $this->active,
            'initial_km' => 0,
        ],
    );

    $this->showModal = false;
};

$openStatus = function (int $id): void {
    $vehicle = Vehicle::query()->findOrFail($id);

    $this->statusVehicleId = $id;
    $this->availableFrom = $vehicle->available_from?->format('Y-m-d\TH:i') ?? '';
    $this->availableUntil = $vehicle->available_until?->format('Y-m-d\TH:i') ?? '';
    $this->inactiveReason = $vehicle->active ? '' : ($vehicle->inactive_reason ?? '');
    $this->lockStart = '';
    $this->lockEnd = '';
    $this->lockReason = '';
    $this->lockStep = 1;
    $this->deactivateStep = 1;
    $this->availabilityStep = 1;
    $this->workshopDriverId = null;
    $this->workshopStart = '';
    $this->workshopEnd = '';
    $this->showStatusModal = true;
};

$updatedAvailableFrom = function (): void {
    $this->availabilityStep = 1;
};

$updatedAvailableUntil = function (): void {
    $this->availabilityStep = 1;
};

$checkAvailabilityConflicts = function (): void {
    if ($this->availableFrom !== '' && $this->availableUntil !== '') {
        $from = \Carbon\Carbon::parse($this->availableFrom);
        $until = \Carbon\Carbon::parse($this->availableUntil);

        if ($until->lt($from)) {
            $this->addError('availableUntil', '„Verfügbar bis“ muss nach „Verfügbar ab“ liegen.');

            return;
        }
    }

    $this->availabilityStep = 2;
};

$resetAvailabilityStep = function (): void {
    $this->availabilityStep = 1;
};

$confirmSaveAvailability = function (): void {
    if (! $this->canConfirmAvailability) {
        return;
    }

    $vehicle = Vehicle::query()->findOrFail($this->statusVehicleId);
    $from = $this->availableFrom !== '' ? \Carbon\Carbon::parse($this->availableFrom) : null;
    $until = $this->availableUntil !== '' ? \Carbon\Carbon::parse($this->availableUntil) : null;

    app(VehicleAdminService::class)->updateAvailability($vehicle, $from, $until);

    Flux::toast(text: 'Verfügbarkeit wurde gespeichert.', variant: 'success');

    $this->availabilityStep = 1;
};

$clearAvailability = function (): void {
    $vehicle = Vehicle::query()->findOrFail($this->statusVehicleId);

    app(VehicleAdminService::class)->updateAvailability($vehicle, null, null);

    $this->availableFrom = '';
    $this->availableUntil = '';
    $this->availabilityStep = 1;

    Flux::toast(text: 'Verfügbarkeitsbeschränkung wurde entfernt.', variant: 'success');
};

$checkDeactivateConflicts = function (): void {
    $this->validate([
        'inactiveReason' => 'required|string|min:3',
    ], [
        'inactiveReason.required' => 'Bitte einen Grund für die Deaktivierung angeben.',
        'inactiveReason.min' => 'Der Grund muss mindestens 3 Zeichen lang sein.',
    ]);

    $this->deactivateStep = 2;
};

$resetDeactivateStep = function (): void {
    $this->deactivateStep = 1;
};

$confirmDeactivate = function (): void {
    if (! $this->canConfirmDeactivate) {
        return;
    }

    $this->validate(['inactiveReason' => 'required|string|min:3']);

    $vehicle = Vehicle::query()->findOrFail($this->statusVehicleId);

    app(VehicleAdminService::class)->deactivate($vehicle, auth()->user(), $this->inactiveReason);

    Flux::toast(text: 'Fahrzeug wurde deaktiviert.', variant: 'success');

    $this->reset(['inactiveReason', 'deactivateStep']);

    unset($this->statusVehicle);
};

$reactivateVehicle = function (): void {
    $vehicle = Vehicle::query()->findOrFail($this->statusVehicleId);

    app(VehicleAdminService::class)->activate($vehicle);

    $this->inactiveReason = '';

    unset($this->statusVehicle);

    Flux::toast(text: 'Fahrzeug wurde reaktiviert.', variant: 'success');
};

$removeLock = function (int $bookingId): void {
    $vehicle = Vehicle::query()->findOrFail($this->statusVehicleId);

    app(VehicleAdminService::class)->removeLockBooking($vehicle, $bookingId);

    Flux::toast(text: 'Sperre wurde aufgehoben.', variant: 'success');

    unset($this->activeLockBookings);
};

$checkLockConflicts = function (): void {
    $this->validate([
        'lockStart' => 'required|date',
        'lockEnd' => 'required|date|after:lockStart',
        'lockReason' => 'required|string|min:1',
    ], [
        'lockStart.required' => 'Bitte Start der Sperre angeben.',
        'lockEnd.required' => 'Bitte Ende der Sperre angeben.',
        'lockEnd.after' => '„Sperre bis“ muss nach „Sperre von“ liegen.',
        'lockReason.required' => 'Bitte einen Sperrgrund angeben.',
    ]);

    $this->lockStep = 2;
};

$resetLockStep = function (): void {
    $this->lockStep = 1;
};

$createLock = function (): void {
    if (! $this->canConfirmLock) {
        return;
    }

    $this->validate([
        'lockStart' => 'required|date',
        'lockEnd' => 'required|date|after:lockStart',
        'lockReason' => 'required|string|min:1',
    ]);

    $vehicle = Vehicle::query()->findOrFail($this->statusVehicleId);
    $start = \Carbon\Carbon::parse($this->lockStart);
    $end = \Carbon\Carbon::parse($this->lockEnd);

    app(VehicleAdminService::class)->createLockBooking($vehicle, auth()->user(), $start, $end, $this->lockReason);

    Flux::toast(text: 'Fahrzeug wurde für den gewählten Zeitraum gesperrt.', variant: 'success');

    $this->reset(['lockStart', 'lockEnd', 'lockReason', 'lockStep']);
    $this->showStatusModal = false;
};

$createWorkshop = function (): void {
    $this->validate([
        'workshopDriverId' => 'required|integer',
        'workshopStart' => 'required|date',
        'workshopEnd' => 'required|date|after:workshopStart',
    ], [
        'workshopDriverId.required' => 'Bitte einen Fahrer auswählen.',
        'workshopStart.required' => 'Bitte Start der Werkstattfahrt angeben.',
        'workshopEnd.required' => 'Bitte Ende der Werkstattfahrt angeben.',
        'workshopEnd.after' => '„Werkstatt bis“ muss nach „Werkstatt von“ liegen.',
    ]);

    $vehicle = Vehicle::query()->findOrFail($this->statusVehicleId);
    app(\Hwkdo\IntranetAppFuhrpark\Services\LogbookService::class)->createWorkshopTrip(
        $vehicle,
        auth()->user(),
        (int) $this->workshopDriverId,
        \Carbon\Carbon::parse($this->workshopStart),
        \Carbon\Carbon::parse($this->workshopEnd),
    );

    Flux::toast(text: 'Werkstattfahrt wurde angelegt.', variant: 'success');

    $this->reset(['workshopDriverId', 'workshopStart', 'workshopEnd']);
    $this->showStatusModal = false;
};

?>

<div>
    <flux:button wire:click="openCreate" class="mb-4">Neues Fahrzeug</flux:button>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Kennzeichen</flux:table.column>
            <flux:table.column>Kategorie</flux:table.column>
            <flux:table.column>Standort</flux:table.column>
            <flux:table.column>Aktiv</flux:table.column>
            <flux:table.column>Verfügbarkeit</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->vehicles as $vehicle)
                <flux:table.row>
                    <flux:table.cell>{{ $vehicle->license_plate }}</flux:table.cell>
                    <flux:table.cell>{{ $vehicle->category->name }}</flux:table.cell>
                    <flux:table.cell>{{ $vehicle->standort->name ?? '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $vehicle->active ? 'Ja' : 'Nein' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($vehicle->hasAvailabilityRestriction())
                            <flux:badge variant="danger" size="sm">{{ $vehicle->availabilityLabel() }}</flux:badge>
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="flex flex-wrap gap-2">
                        <flux:button size="sm" wire:click="openEdit({{ $vehicle->id }})">Bearbeiten</flux:button>
                        <flux:button size="sm" wire:click="openStatus({{ $vehicle->id }})">Status</flux:button>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            href="{{ route('apps.fuhrpark.admin.vehicles.logbook-pdf', $vehicle) }}"
                            target="_blank"
                        >
                            Fahrtenbuch PDF
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showModal" class="md:w-lg">
        <flux:heading size="lg">{{ $editingId ? 'Fahrzeug bearbeiten' : 'Fahrzeug anlegen' }}</flux:heading>
        <div class="mt-4 space-y-4">
            <flux:input wire:model="licensePlate" label="Kennzeichen" />
            <flux:select wire:model="categoryId" label="Kategorie">
                @foreach($this->categories as $cat)
                    <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="standortId" label="Standort">
                @foreach($this->standorte as $standort)
                    <flux:select.option value="{{ $standort->id }}">{{ $standort->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="manufacturer" label="Hersteller" />
            <flux:input wire:model="model" label="Modell" />
            <flux:select wire:model.live="fuelType" label="Kraftstoff">
                <flux:select.option value="petrol">Benzin</flux:select.option>
                <flux:select.option value="diesel">Diesel</flux:select.option>
                <flux:select.option value="electric">Elektro</flux:select.option>
            </flux:select>
            @if ($fuelType === 'electric')
                <flux:input wire:model="electricRangeKm" type="number" min="1" label="Reichweite (km)" />
                <flux:input wire:model="electricChargeMinutes" type="number" min="1" label="Ladezeit (Minuten)" />
            @endif
            <flux:checkbox wire:model.boolean="active" label="Aktiv" />
            <flux:button variant="primary" wire:click="save">Speichern</flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="showStatusModal" class="md:w-xl">
        <flux:heading size="lg">Fahrzeug-Status</flux:heading>
        <div class="mt-4 space-y-6">
            @if ($this->statusVehicle && ! $this->statusVehicle->active)
                <flux:callout variant="warning">
                    <flux:callout.heading>Fahrzeug deaktiviert</flux:callout.heading>
                    <flux:callout.text>
                        @if ($this->statusVehicle->inactive_reason)
                            Grund: {{ $this->statusVehicle->inactive_reason }}
                        @else
                            Das Fahrzeug ist derzeit nicht aktiv.
                        @endif
                    </flux:callout.text>
                </flux:callout>
                <flux:button variant="primary" wire:click="reactivateVehicle">Reaktivieren</flux:button>
            @else
                <div>
                    <flux:heading size="sm">Deaktivieren</flux:heading>

                    @if ($deactivateStep === 1)
                        <div class="mt-3 space-y-3">
                            <flux:textarea wire:model="inactiveReason" label="Grund" />
                            <flux:button variant="primary" wire:click="checkDeactivateConflicts">Prüfen</flux:button>
                        </div>
                    @else
                        <div class="mt-3 space-y-4">
                            <flux:text class="text-sm text-zinc-500">
                                Grund: {{ $inactiveReason }}
                            </flux:text>

                            @include('intranet-app-fuhrpark::livewire.apps.fuhrpark.admin.partials.vehicle-status-booking-conflicts', [
                                'bookings' => $this->deactivateConflictsRemaining,
                                'vehicleId' => $statusVehicleId,
                                'warningMessage' => 'Es bestehen noch '.$this->deactivateConflictsRemaining->count().' '
                                    .($this->deactivateConflictsRemaining->count() === 1 ? 'Buchung' : 'Buchungen')
                                    .' für dieses Fahrzeug. Bitte jede Buchung umbuchen oder löschen.',
                                'emptyMessage' => 'Keine kollidierenden Buchungen. Das Fahrzeug kann deaktiviert werden.',
                            ])

                            <div class="flex flex-wrap gap-2">
                                <flux:button variant="ghost" wire:click="resetDeactivateStep">Zurück</flux:button>
                                @if ($this->canConfirmDeactivate)
                                    <flux:button variant="danger" wire:click="confirmDeactivate">Deaktivieren</flux:button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div>
                <flux:heading size="sm">Sperren</flux:heading>

                @if ($this->activeLockBookings->isNotEmpty())
                    <div class="mt-3 space-y-2">
                        <flux:text class="text-sm text-zinc-500">Aktive Sperren</flux:text>
                        @foreach ($this->activeLockBookings as $lockBooking)
                            <div
                                wire:key="active-lock-{{ $lockBooking->id }}"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
                            >
                                <flux:text class="text-sm">
                                    {{ $lockBooking->starts_at->format('d.m.Y H:i') }}
                                    –
                                    {{ $lockBooking->ends_at->format('d.m.Y H:i') }}
                                    @if ($lockBooking->lock_reason)
                                        · {{ $lockBooking->lock_reason }}
                                    @endif
                                </flux:text>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    wire:click="removeLock({{ $lockBooking->id }})"
                                >
                                    Aufheben
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($lockStep === 1)
                    <div class="mt-3 space-y-3">
                        <flux:input wire:model="lockStart" type="datetime-local" label="Sperre von" />
                        <flux:input wire:model="lockEnd" type="datetime-local" label="Sperre bis" />
                        <flux:input wire:model="lockReason" label="Sperrgrund" />
                        <flux:button variant="primary" wire:click="checkLockConflicts">Prüfen</flux:button>
                    </div>
                @else
                    <div class="mt-3 space-y-4">
                        <flux:text class="text-sm text-zinc-500">
                            Sperre von {{ \Carbon\Carbon::parse($lockStart)->format('d.m.Y H:i') }}
                            bis {{ \Carbon\Carbon::parse($lockEnd)->format('d.m.Y H:i') }}
                            · Grund: {{ $lockReason }}
                        </flux:text>

                        @include('intranet-app-fuhrpark::livewire.apps.fuhrpark.admin.partials.vehicle-status-booking-conflicts', [
                            'bookings' => $this->lockConflictsRemaining,
                            'vehicleId' => $statusVehicleId,
                            'warningMessage' => 'Das Fahrzeug hat '.$this->lockConflictsRemaining->count().' '
                                .($this->lockConflictsRemaining->count() === 1 ? 'Buchung' : 'Buchungen')
                                .' im Sperrzeitraum. Bitte jede Buchung umbuchen oder löschen.',
                            'emptyMessage' => 'Keine kollidierenden Buchungen. Das Fahrzeug kann gesperrt werden.',
                        ])

                        <div class="flex flex-wrap gap-2">
                            <flux:button variant="ghost" wire:click="resetLockStep">Zurück</flux:button>
                            @if ($this->canConfirmLock)
                                <flux:button variant="danger" wire:click="createLock">Fahrzeug sperren</flux:button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div>
                <flux:heading size="sm">Werkstattfahrt</flux:heading>
                <div class="mt-3 space-y-3">
                    <flux:select
                        wire:model="workshopDriverId"
                        variant="listbox"
                        searchable
                        label="Fahrer"
                        placeholder="Fahrer auswählen…"
                    >
                        @foreach ($this->workshopDrivers as $user)
                            <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="workshopStart" type="datetime-local" label="Werkstatt von" />
                    <flux:input wire:model="workshopEnd" type="datetime-local" label="Werkstatt bis" />
                    <flux:button wire:click="createWorkshop">Werkstattfahrt anlegen</flux:button>
                </div>
            </div>

            <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:heading size="sm">Verfügbarkeit</flux:heading>
                <flux:text class="mt-1">Leer lassen = unbegrenzt verfügbar.</flux:text>

                @if ($this->statusVehicle?->hasAvailabilityRestriction())
                    <flux:callout variant="warning" class="mt-3">
                        <flux:callout.text>
                            Aktuelle Beschränkung: {{ $this->statusVehicle->availabilityLabel() }}
                        </flux:callout.text>
                    </flux:callout>
                    <flux:button class="mt-2" variant="ghost" wire:click="clearAvailability">
                        Beschränkung aufheben
                    </flux:button>
                @endif

                @if ($availabilityStep === 1)
                    <div class="mt-3 space-y-3">
                        <flux:input wire:model.live="availableFrom" type="datetime-local" label="Verfügbar ab" />
                        <flux:input wire:model.live="availableUntil" type="datetime-local" label="Verfügbar bis" />
                        <flux:button variant="primary" wire:click="checkAvailabilityConflicts">Prüfen</flux:button>
                    </div>
                @else
                    <div class="mt-3 space-y-4">
                        <flux:text class="text-sm text-zinc-500">
                            @if ($availableFrom !== '')
                                Verfügbar ab {{ \Carbon\Carbon::parse($availableFrom)->format('d.m.Y H:i') }}
                            @else
                                Kein Startdatum
                            @endif
                            ·
                            @if ($availableUntil !== '')
                                bis {{ \Carbon\Carbon::parse($availableUntil)->format('d.m.Y H:i') }}
                            @else
                                unbegrenzt
                            @endif
                        </flux:text>

                        @include('intranet-app-fuhrpark::livewire.apps.fuhrpark.admin.partials.vehicle-status-booking-conflicts', [
                            'bookings' => $this->availabilityConflictsRemaining,
                            'vehicleId' => $statusVehicleId,
                            'warningMessage' => 'Im gewählten Verfügbarkeitszeitraum liegen '
                                .$this->availabilityConflictsRemaining->count().' '
                                .($this->availabilityConflictsRemaining->count() === 1 ? 'Buchung' : 'Buchungen')
                                .' außerhalb der Freigabe. Bitte jede Buchung umbuchen oder löschen.',
                            'emptyMessage' => 'Keine kollidierenden Buchungen. Die Verfügbarkeit kann gespeichert werden.',
                        ])

                        <div class="flex flex-wrap gap-2">
                            <flux:button variant="ghost" wire:click="resetAvailabilityStep">Zurück</flux:button>
                            @if ($this->canConfirmAvailability)
                                <flux:button variant="primary" wire:click="confirmSaveAvailability">
                                    Verfügbarkeit speichern
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
