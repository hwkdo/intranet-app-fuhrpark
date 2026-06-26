<?php

use Hwkdo\IntranetAppFuhrpark\Models\VehicleCategory;
use function Livewire\Volt\{computed, state};

state([
    'showModal' => false,
    'editingId' => null,
    'name' => '',
    'requiresLicense' => true,
    'isElectric' => false,
    'electricRangeAvgKm' => null,
    'electricChargeMinutesAvg' => null,
]);

$categories = computed(fn () => VehicleCategory::query()->orderBy('name')->get());

$openCreate = function (): void {
    $this->reset([
        'editingId',
        'name',
        'requiresLicense',
        'isElectric',
        'electricRangeAvgKm',
        'electricChargeMinutesAvg',
    ]);
    $this->requiresLicense = true;
    $this->isElectric = false;
    $this->showModal = true;
};

$openEdit = function (int $id): void {
    $cat = VehicleCategory::query()->findOrFail($id);
    $this->editingId = $id;
    $this->name = $cat->name;
    $this->requiresLicense = $cat->requires_license;
    $this->isElectric = $cat->is_electric;
    $this->electricRangeAvgKm = $cat->electric_range_avg_km;
    $this->electricChargeMinutesAvg = $cat->electric_charge_minutes_avg;
    $this->showModal = true;
};

$save = function (): void {
    $rules = ['name' => 'required|string|max:100'];

    if ($this->isElectric) {
        $rules['electricRangeAvgKm'] = 'required|integer|min:1';
        $rules['electricChargeMinutesAvg'] = 'required|integer|min:1';
    }

    $this->validate($rules);

    $isElectric = (bool) $this->isElectric;

    VehicleCategory::query()->updateOrCreate(
        ['id' => $this->editingId],
        [
            'name' => $this->name,
            'requires_license' => (bool) $this->requiresLicense,
            'is_electric' => $isElectric,
            'electric_range_avg_km' => $isElectric ? (int) $this->electricRangeAvgKm : null,
            'electric_charge_minutes_avg' => $isElectric ? (int) $this->electricChargeMinutesAvg : null,
        ],
    );

    $this->showModal = false;
};

?>

<div>
    <flux:button wire:click="openCreate" class="mb-4">Neue Kategorie</flux:button>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Führerschein</flux:table.column>
            <flux:table.column>Elektro</flux:table.column>
            <flux:table.column>Reichweite Ø</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->categories as $category)
                <flux:table.row>
                    <flux:table.cell>{{ $category->name }}</flux:table.cell>
                    <flux:table.cell>{{ $category->requires_license ? 'Ja' : 'Nein' }}</flux:table.cell>
                    <flux:table.cell>{{ $category->is_electric ? 'Ja' : 'Nein' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($category->is_electric && $category->electric_range_avg_km)
                            {{ $category->electric_range_avg_km }} km
                        @else
                            –
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" wire:click="openEdit({{ $category->id }})">Bearbeiten</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showModal" class="md:w-lg">
        <flux:heading size="lg">Kategorie</flux:heading>
        <div class="mt-4 space-y-4">
            <flux:input wire:model="name" label="Name" />
            <flux:checkbox wire:model.live.boolean="isElectric" label="Elektro-Kategorie" />
            @if ($isElectric)
                <flux:input wire:model="electricRangeAvgKm" type="number" min="1" label="Durchschn. Reichweite (km)" />
                <flux:input wire:model="electricChargeMinutesAvg" type="number" min="1" label="Durchschn. Ladezeit (Minuten)" />
            @endif
            <flux:checkbox wire:model.boolean="requiresLicense" label="Führerschein erforderlich" />
            <flux:button variant="primary" wire:click="save">Speichern</flux:button>
        </div>
    </flux:modal>
</div>
