<?php

use Flux\Flux;
use Hwkdo\IntranetAppFuhrpark\Enums\BookingPurpose;
use Hwkdo\IntranetAppFuhrpark\Enums\VehicleAdminDisplayStatus;
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
    'statusVehicleId' => null,
    'showDeactivateModal' => false,
    'showLockModal' => false,
    'showWorkshopModal' => false,
    'showAvailabilityModal' => false,
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
    'showStatusFilters' => [
        'available' => true,
        'underway' => true,
        'limited' => true,
        'unavailable' => true,
    ],
]);

$allVehicles = computed(fn () => Vehicle::query()
    ->with(['category', 'standort'])
    ->withCount([
        'bookings as active_locks_count' => fn ($query) => $query
            ->where('purpose', BookingPurpose::Lock)
            ->where('ends_at', '>', now()),
        'bookings as running_workshop_count' => fn ($query) => $query
            ->where('purpose', BookingPurpose::Workshop)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now()),
    ])
    ->orderBy('license_plate')
    ->get());

$vehicles = computed(function () {
    return $this->allVehicles
        ->filter(function (Vehicle $vehicle): bool {
            $status = $vehicle->adminDisplayStatus();

            return (bool) ($this->showStatusFilters[$status->value] ?? true);
        })
        ->values();
});

$statusFilterCounts = computed(fn () => $this->allVehicles->countBy(
    fn (Vehicle $vehicle): string => $vehicle->adminDisplayStatus()->value,
));

$activeStatusFilterCount = computed(fn () => collect($this->showStatusFilters)->filter()->count());

$toggleStatusFilter = function (string $status): void {
    $this->showStatusFilters[$status] = ! ($this->showStatusFilters[$status] ?? true);
};

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

$activeWorkshopBooking = computed(function () {
    $vehicle = $this->statusVehicle;

    if (! $vehicle) {
        return null;
    }

    return app(VehicleAdminService::class)->activeWorkshopBooking($vehicle);
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
        $this->activeWorkshopBooking,
        $this->allVehicles,
        $this->vehicles,
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

$prepareStatusVehicle = function (int $id): void {
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

    unset($this->statusVehicle, $this->activeLockBookings, $this->activeWorkshopBooking, $this->allVehicles, $this->vehicles);
};

$openDeactivateModal = function (int $id): void {
    $this->prepareStatusVehicle($id);
    $this->showDeactivateModal = true;
};

$openLockModal = function (int $id): void {
    $this->prepareStatusVehicle($id);
    $this->showLockModal = true;
};

$openWorkshopModal = function (int $id): void {
    $this->prepareStatusVehicle($id);
    $this->showWorkshopModal = true;
};

$openAvailabilityModal = function (int $id): void {
    $this->prepareStatusVehicle($id);
    $this->showAvailabilityModal = true;
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
    $this->showAvailabilityModal = false;

    unset($this->statusVehicle, $this->allVehicles, $this->vehicles);
};

$clearAvailability = function (): void {
    $vehicle = Vehicle::query()->findOrFail($this->statusVehicleId);

    app(VehicleAdminService::class)->updateAvailability($vehicle, null, null);

    $this->availableFrom = '';
    $this->availableUntil = '';
    $this->availabilityStep = 1;

    Flux::toast(text: 'Verfügbarkeitsbeschränkung wurde entfernt.', variant: 'success');

    unset($this->statusVehicle, $this->allVehicles, $this->vehicles);
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
    $this->showDeactivateModal = false;

    unset($this->statusVehicle, $this->allVehicles, $this->vehicles);
};

$reactivateVehicle = function (): void {
    $vehicle = Vehicle::query()->findOrFail($this->statusVehicleId);

    app(VehicleAdminService::class)->activate($vehicle);

    $this->inactiveReason = '';
    $this->showDeactivateModal = false;

    unset($this->statusVehicle, $this->allVehicles, $this->vehicles);

    Flux::toast(text: 'Fahrzeug wurde reaktiviert.', variant: 'success');
};

$removeLock = function (int $bookingId): void {
    $vehicle = Vehicle::query()->findOrFail($this->statusVehicleId);

    app(VehicleAdminService::class)->removeLockBooking($vehicle, $bookingId);

    Flux::toast(text: 'Sperre wurde aufgehoben.', variant: 'success');

    unset($this->activeLockBookings, $this->allVehicles, $this->vehicles);
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
    $this->showLockModal = false;

    unset($this->activeLockBookings, $this->allVehicles, $this->vehicles);
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
    $this->showWorkshopModal = false;

    unset($this->activeWorkshopBooking, $this->allVehicles, $this->vehicles);
};

?>

<div>
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <flux:button wire:click="openCreate">Neues Fahrzeug</flux:button>

        <flux:dropdown position="bottom" align="start">
            <flux:button variant="ghost" icon="funnel" icon-trailing="chevron-down">
                Statusfilter
                @if ($this->activeStatusFilterCount < count(VehicleAdminDisplayStatus::cases()))
                    <flux:badge size="sm" class="ms-1">{{ $this->activeStatusFilterCount }}/{{ count(VehicleAdminDisplayStatus::cases()) }}</flux:badge>
                @endif
            </flux:button>

            <flux:menu>
                @foreach (VehicleAdminDisplayStatus::cases() as $status)
                    @php($isActive = $showStatusFilters[$status->value] ?? true)
                    @php($count = $this->statusFilterCounts[$status->value] ?? 0)
                    <flux:menu.item
                        wire:click="toggleStatusFilter('{{ $status->value }}')"
                        wire:key="status-filter-{{ $status->value }}"
                        :icon="$isActive ? 'check-circle' : 'minus-circle'"
                        @class([
                            'opacity-60' => ! $isActive,
                        ])
                    >
                        <span class="flex items-center gap-2">
                            <span
                                class="size-2 shrink-0 rounded-full {{ $status->filterDotClass() }} {{ ! $isActive ? 'opacity-40' : '' }}"
                                aria-hidden="true"
                            ></span>
                            {{ $status->label() }} ({{ $count }})
                        </span>
                    </flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Kennzeichen</flux:table.column>
            <flux:table.column>Kategorie</flux:table.column>
            <flux:table.column>Standort</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Verfügbarkeit</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->vehicles as $vehicle)
                @php($displayStatus = $vehicle->adminDisplayStatus())
                <flux:table.row wire:key="vehicle-row-{{ $vehicle->id }}" class="{{ $displayStatus->rowClass() }}">
                    <flux:table.cell class="font-medium">{{ $vehicle->license_plate }}</flux:table.cell>
                    <flux:table.cell>{{ $vehicle->category->name }}</flux:table.cell>
                    <flux:table.cell>{{ $vehicle->standort->name ?? '-' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$displayStatus->badgeColor()" size="sm">
                            {{ $displayStatus->label() }}
                        </flux:badge>
                        @if (! $vehicle->active && $vehicle->inactive_reason)
                            <flux:text class="mt-1 block text-xs text-zinc-500">{{ $vehicle->inactive_reason }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($vehicle->hasAvailabilityRestriction())
                            <flux:badge color="amber" size="sm">{{ $vehicle->availabilityLabel() }}</flux:badge>
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="flex flex-wrap gap-2">
                        <flux:button size="sm" wire:click="openEdit({{ $vehicle->id }})">Bearbeiten</flux:button>
                        <flux:dropdown position="bottom" align="end">
                            <flux:button
                                size="sm"
                                icon-trailing="chevron-down"
                                :variant="$displayStatus->statusButtonVariant()"
                                class="{{ $displayStatus->statusButtonClass() }}"
                            >
                                Status
                            </flux:button>
                            <flux:menu>
                                <flux:menu.item
                                    wire:click="openDeactivateModal({{ $vehicle->id }})"
                                    variant="{{ $vehicle->adminStatusMenuIsUrgent('deactivate') ? 'danger' : 'default' }}"
                                    @class([
                                        'text-red-600! dark:text-red-400!' => $vehicle->adminStatusMenuIsUrgent('deactivate'),
                                    ])
                                    icon="{{ $vehicle->active ? 'x-circle' : 'check-circle' }}"
                                >
                                    {{ $vehicle->active ? 'Deaktivieren' : 'Aktivieren' }}
                                </flux:menu.item>
                                <flux:menu.item
                                    wire:click="openLockModal({{ $vehicle->id }})"
                                    variant="{{ $vehicle->adminStatusMenuIsUrgent('lock') ? 'danger' : 'default' }}"
                                    @class([
                                        'text-red-600! dark:text-red-400!' => $vehicle->adminStatusMenuIsUrgent('lock'),
                                    ])
                                    icon="{{ $vehicle->active_locks_count > 0 ? 'lock-open' : 'lock-closed' }}"
                                >
                                    {{ $vehicle->active_locks_count > 0 ? 'Entsperren' : 'Sperren' }}
                                </flux:menu.item>
                                <flux:menu.item
                                    wire:click="openWorkshopModal({{ $vehicle->id }})"
                                    variant="{{ $vehicle->adminStatusMenuIsUrgent('workshop') ? 'danger' : 'default' }}"
                                    @class([
                                        'text-red-600! dark:text-red-400!' => $vehicle->adminStatusMenuIsUrgent('workshop'),
                                    ])
                                    icon="wrench-screwdriver"
                                >
                                    @if ($vehicle->running_workshop_count > 0)
                                        Werkstattfahrt (läuft)
                                    @else
                                        Werkstattfahrt
                                    @endif
                                </flux:menu.item>
                                <flux:menu.item
                                    wire:click="openAvailabilityModal({{ $vehicle->id }})"
                                    variant="{{ $vehicle->adminStatusMenuIsUrgent('availability') ? 'danger' : 'default' }}"
                                    @class([
                                        'text-red-600! dark:text-red-400!' => $vehicle->adminStatusMenuIsUrgent('availability'),
                                        'text-amber-700! dark:text-amber-300!' => ! $vehicle->adminStatusMenuIsUrgent('availability') && $vehicle->hasAvailabilityRestriction(),
                                    ])
                                    icon="calendar-days"
                                >
                                    {{ $vehicle->hasAvailabilityRestriction() ? 'Verfügbarkeit ändern' : 'Verfügbarkeit' }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
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
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">
                        Keine Fahrzeuge für die gewählten Filter.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
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

    @include('intranet-app-fuhrpark::livewire.apps.fuhrpark.admin.partials.vehicle-status-deactivate-modal')
    @include('intranet-app-fuhrpark::livewire.apps.fuhrpark.admin.partials.vehicle-status-lock-modal')
    @include('intranet-app-fuhrpark::livewire.apps.fuhrpark.admin.partials.vehicle-status-workshop-modal')
    @include('intranet-app-fuhrpark::livewire.apps.fuhrpark.admin.partials.vehicle-status-availability-modal')
</div>
