<?php

use Hwkdo\IntranetAppFuhrpark\Models\Booking;
use Hwkdo\IntranetAppFuhrpark\Models\Vehicle;
use function Livewire\Volt\{computed, mount, state, title};

title('Fuhrpark - Admin');

state(['activeTab' => 'fahrzeuge']);

mount(function (): void {
    $tab = request()->query('tab');

    if (is_string($tab) && in_array($tab, [
        'fahrzeuge',
        'kategorien',
        'projekte',
        'buchungen',
        'standorte',
        'einstellungen',
        'hintergrundbild',
        'statistiken',
    ], true)) {
        $this->activeTab = $tab;
    }
});

$stats = computed(function (): array {
    return [
        'vehicles' => Vehicle::query()->where('active', true)->count(),
        'bookings_month' => Booking::query()->where('starts_at', '>=', now()->startOfMonth())->count(),
        'bookings_open' => Booking::query()->where('ends_at', '>=', now())->count(),
    ];
});

?>

<x-intranet-app-fuhrpark::fuhrpark-layout heading="Fuhrpark Admin" subheading="Stammdaten und Einstellungen">
    <flux:tab.group>
        <flux:tabs wire:model.live="activeTab">
            <flux:tab name="fahrzeuge" icon="truck">Fahrzeuge</flux:tab>
            <flux:tab name="kategorien" icon="tag">Kategorien</flux:tab>
            <flux:tab name="projekte" icon="folder">Projekte</flux:tab>
            <flux:tab name="buchungen" icon="calendar">Buchungen</flux:tab>
            <flux:tab name="standorte" icon="map-pin">Standorte</flux:tab>
            <flux:tab name="einstellungen" icon="cog-6-tooth">Einstellungen</flux:tab>
            <flux:tab name="hintergrundbild" icon="photo">Hintergrund</flux:tab>
            <flux:tab name="statistiken" icon="chart-bar">Statistik</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="fahrzeuge">
            @if ($activeTab === 'fahrzeuge')
                <div class="min-h-[400px]">
                    <livewire:apps.fuhrpark.admin.vehicles lazy />
                </div>
            @endif
        </flux:tab.panel>

        <flux:tab.panel name="kategorien">
            @if ($activeTab === 'kategorien')
                <div class="min-h-[400px]">
                    <livewire:apps.fuhrpark.admin.categories lazy />
                </div>
            @endif
        </flux:tab.panel>

        <flux:tab.panel name="projekte">
            @if ($activeTab === 'projekte')
                <div class="min-h-[400px]">
                    <livewire:apps.fuhrpark.admin.projects lazy />
                </div>
            @endif
        </flux:tab.panel>

        <flux:tab.panel name="buchungen">
            @if ($activeTab === 'buchungen')
                <div class="min-h-[400px]">
                    <livewire:apps.fuhrpark.admin.bookings lazy />
                </div>
            @endif
        </flux:tab.panel>

        <flux:tab.panel name="standorte">
            @if ($activeTab === 'standorte')
                <div class="min-h-[400px]">
                    <livewire:apps.fuhrpark.admin.standorte lazy />
                </div>
            @endif
        </flux:tab.panel>

        <flux:tab.panel name="einstellungen">
            @if ($activeTab === 'einstellungen')
                <div class="min-h-[400px]">
                    @livewire('intranet-app-base::admin-settings', [
                        'appIdentifier' => 'fuhrpark',
                        'settingsModelClass' => '\Hwkdo\IntranetAppFuhrpark\Models\IntranetAppFuhrparkSettings',
                        'appSettingsClass' => '\Hwkdo\IntranetAppFuhrpark\Data\AppSettings',
                    ], key('fuhrpark-admin-settings'))
                </div>
            @endif
        </flux:tab.panel>

        <flux:tab.panel name="hintergrundbild">
            @if ($activeTab === 'hintergrundbild')
                <div class="min-h-[400px]">
                    @livewire('intranet-app-base::app-background-image', ['appIdentifier' => 'fuhrpark'], key('fuhrpark-admin-background'))
                </div>
            @endif
        </flux:tab.panel>

        <flux:tab.panel name="statistiken">
            <div class="grid gap-4 md:grid-cols-3">
                <flux:card>
                    <flux:heading size="md">Aktive Fahrzeuge</flux:heading>
                    <flux:text size="xl" class="mt-2">{{ $this->stats['vehicles'] }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="md">Buchungen (Monat)</flux:heading>
                    <flux:text size="xl" class="mt-2">{{ $this->stats['bookings_month'] }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="md">Offene Buchungen</flux:heading>
                    <flux:text size="xl" class="mt-2">{{ $this->stats['bookings_open'] }}</flux:text>
                </flux:card>
            </div>
        </flux:tab.panel>
    </flux:tab.group>
</x-intranet-app-fuhrpark::fuhrpark-layout>
