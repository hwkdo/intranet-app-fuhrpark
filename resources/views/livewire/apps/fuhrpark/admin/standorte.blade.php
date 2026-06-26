<?php

use Flux\Flux;
use Hwkdo\IntranetAppFuhrpark\Services\StandortAdminService;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use function Livewire\Volt\{computed, mount, state};

state([
    'assignmentByStandortId' => [],
]);

$standorte = computed(fn () => app(StandortAdminService::class)->allForAdmin());

$vehicleStandorte = computed(fn () => app(StandortAdminService::class)->vehicleStandorte());

$rebuildAssignments = function (): void {
    $this->assignmentByStandortId = app(StandortAdminService::class)
        ->allForAdmin()
        ->mapWithKeys(fn ($entry): array => [$entry->standort->id => $entry->vehicleStandortId])
        ->all();
};

$toggleVehicleStandort = function (int $standortId): void {
    $standort = FuhrparkModels::standortQuery()->findOrFail($standortId);
    $entry = app(StandortAdminService::class)->allForAdmin()
        ->first(fn ($item): bool => (int) $item->standort->id === $standortId);

    app(StandortAdminService::class)->setVehicleStandort(
        $standort,
        ! ($entry?->isVehicleStandort ?? false),
    );

    Flux::toast(
        text: ($entry?->isVehicleStandort ?? false)
            ? 'Fahrzeugstandort-Markierung wurde entfernt.'
            : 'Standort wurde als Fahrzeugstandort markiert.',
        variant: 'success',
    );

    unset($this->standorte, $this->vehicleStandorte);
    $this->rebuildAssignments();
};

$saveAssignment = function (int $standortId): void {
    $standort = FuhrparkModels::standortQuery()->findOrFail($standortId);
    $vehicleStandortId = $this->assignmentByStandortId[$standortId] ?? null;

    if ($vehicleStandortId === '') {
        $vehicleStandortId = null;
    }

    app(StandortAdminService::class)->assignVehicleStandort(
        $standort,
        $vehicleStandortId !== null ? (int) $vehicleStandortId : null,
    );

    Flux::toast(text: 'Standort-Zuordnung wurde gespeichert.', variant: 'success');

    unset($this->standorte, $this->vehicleStandorte);
    $this->rebuildAssignments();
};

mount(function (): void {
    $this->rebuildAssignments();
});

?>

<div>
    <flux:text class="mb-4 text-zinc-500">
        Fahrzeugstandorte stehen beim Buchen zur Auswahl. Weitere Standorte können einem Fahrzeugstandort zugeordnet werden,
        damit Nutzer dort automatisch den passenden Fahrzeugstandort vorausgewählt bekommen.
    </flux:text>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Standort</flux:table.column>
            <flux:table.column>Fahrzeugstandort</flux:table.column>
            <flux:table.column>Zugeordnet zu</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->standorte as $entry)
                <flux:table.row wire:key="standort-{{ $entry->standort->id }}">
                    <flux:table.cell>{{ $entry->standort->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:switch
                            wire:click="toggleVehicleStandort({{ $entry->standort->id }})"
                            :checked="$entry->isVehicleStandort"
                        />
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($entry->isVehicleStandort)
                            <flux:text class="text-sm text-zinc-500">—</flux:text>
                        @else
                            <flux:select
                                wire:model="assignmentByStandortId.{{ $entry->standort->id }}"
                                variant="listbox"
                                searchable
                                placeholder="Fahrzeugstandort wählen…"
                            >
                                <flux:select.option value="">Keine Zuordnung</flux:select.option>
                                @foreach ($this->vehicleStandorte as $vehicleStandort)
                                    @if ($vehicleStandort->id !== $entry->standort->id)
                                        <flux:select.option value="{{ $vehicleStandort->id }}">
                                            {{ $vehicleStandort->name }}
                                        </flux:select.option>
                                    @endif
                                @endforeach
                            </flux:select>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if (! $entry->isVehicleStandort)
                            <flux:button size="sm" wire:click="saveAssignment({{ $entry->standort->id }})">
                                Zuordnung speichern
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
