<?php

use Hwkdo\IntranetAppFuhrpark\Services\FuhrparkAdminStatisticsService;
use function Livewire\Volt\{computed, state};

state(['period' => 'month']);

$statistics = computed(function (): array {
    return app(FuhrparkAdminStatisticsService::class)->collect($this->period);
});

?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="lg">Fuhrpark-Statistik</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ $this->statistics['period_label'] }}</flux:text>
        </div>

        <flux:select wire:model.live="period" class="w-full sm:w-56">
            <flux:select.option value="month">Aktueller Monat</flux:select.option>
            <flux:select.option value="year">Aktuelles Jahr</flux:select.option>
            <flux:select.option value="all">Gesamt</flux:select.option>
        </flux:select>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" class="text-zinc-500">Gefahrene Kilometer</flux:text>
            <div class="text-3xl font-bold">{{ number_format($this->statistics['overview']['total_km'], 0, ',', '.') }} km</div>
            <flux:text size="sm" class="text-zinc-500">
                {{ number_format($this->statistics['overview']['completed_trips'], 0, ',', '.') }} abgeschlossene Fahrten
            </flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" class="text-zinc-500">Ø Kilometer pro Fahrt</flux:text>
            <div class="text-3xl font-bold">{{ number_format($this->statistics['overview']['average_km_per_trip'], 1, ',', '.') }} km</div>
            <flux:text size="sm" class="text-zinc-500">bei erfasstem Kilometerstand</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" class="text-zinc-500">Buchungen</flux:text>
            <div class="text-3xl font-bold">{{ number_format($this->statistics['overview']['total_bookings'], 0, ',', '.') }}</div>
            <flux:text size="sm" class="text-zinc-500">
                {{ number_format($this->statistics['overview']['commute_bookings'], 0, ',', '.') }} Arbeitsfahrten
            </flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" class="text-zinc-500">Aktive Fahrzeuge</flux:text>
            <div class="text-3xl font-bold">{{ number_format($this->statistics['overview']['active_vehicles'], 0, ',', '.') }}</div>
            <flux:text size="sm" class="text-zinc-500">im Fuhrparkbestand</flux:text>
        </flux:card>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <flux:card class="flex flex-col gap-2">
            <flux:heading size="sm" class="text-zinc-500">Meiste Kilometer (Fahrzeug)</flux:heading>
            @if ($this->statistics['top_vehicle_by_km'])
                <div class="text-2xl font-bold">{{ $this->statistics['top_vehicle_by_km']['license_plate'] }}</div>
                <flux:text size="sm">
                    {{ number_format($this->statistics['top_vehicle_by_km']['km'], 0, ',', '.') }} km
                    · {{ $this->statistics['top_vehicle_by_km']['trips'] }} Fahrten
                </flux:text>
            @else
                <flux:text>Keine Kilometerdaten im Zeitraum</flux:text>
            @endif
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:heading size="sm" class="text-zinc-500">Meiste Buchungen (Fahrzeug)</flux:heading>
            @if ($this->statistics['top_vehicle_by_bookings'])
                <div class="text-2xl font-bold">{{ $this->statistics['top_vehicle_by_bookings']['license_plate'] }}</div>
                <flux:text size="sm">{{ $this->statistics['top_vehicle_by_bookings']['bookings'] }} Buchungen</flux:text>
            @else
                <flux:text>Keine Buchungen im Zeitraum</flux:text>
            @endif
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:heading size="sm" class="text-zinc-500">Meiste Kilometer (Fahrer)</flux:heading>
            @if ($this->statistics['top_driver_by_km'])
                <div class="text-2xl font-bold">{{ $this->statistics['top_driver_by_km']['name'] }}</div>
                <flux:text size="sm">
                    {{ number_format($this->statistics['top_driver_by_km']['km'], 0, ',', '.') }} km
                    · {{ $this->statistics['top_driver_by_km']['trips'] }} Fahrten
                </flux:text>
            @else
                <flux:text>Keine Kilometerdaten im Zeitraum</flux:text>
            @endif
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:heading size="sm" class="text-zinc-500">Meiste Buchungen (Fahrer)</flux:heading>
            @if ($this->statistics['top_driver_by_bookings'])
                <div class="text-2xl font-bold">{{ $this->statistics['top_driver_by_bookings']['name'] }}</div>
                <flux:text size="sm">{{ $this->statistics['top_driver_by_bookings']['bookings'] }} Buchungen</flux:text>
            @else
                <flux:text>Keine Buchungen im Zeitraum</flux:text>
            @endif
        </flux:card>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" class="text-zinc-500">Ausgaben</flux:text>
            <div class="text-2xl font-bold">{{ number_format($this->statistics['overview']['handouts'], 0, ',', '.') }}</div>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" class="text-zinc-500">Rückgaben</flux:text>
            <div class="text-2xl font-bold">{{ number_format($this->statistics['overview']['returns'], 0, ',', '.') }}</div>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" class="text-zinc-500">Elektro-Strecke</flux:text>
            <div class="text-2xl font-bold">{{ number_format($this->statistics['overview']['electric_route_km'], 0, ',', '.') }} km</div>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" class="text-zinc-500">Verbrenner-Strecke</flux:text>
            <div class="text-2xl font-bold">{{ number_format($this->statistics['overview']['combustion_route_km'], 0, ',', '.') }} km</div>
        </flux:card>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <flux:card class="space-y-4">
            <flux:heading size="md">Top 5 Fahrzeuge nach Kilometern</flux:heading>

            @if (count($this->statistics['top_vehicles_by_km']) > 0)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>#</flux:table.column>
                        <flux:table.column>Kennzeichen</flux:table.column>
                        <flux:table.column>Kilometer</flux:table.column>
                        <flux:table.column>Fahrten</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->statistics['top_vehicles_by_km'] as $index => $vehicle)
                            <flux:table.row>
                                <flux:table.cell>{{ $index + 1 }}</flux:table.cell>
                                <flux:table.cell class="font-medium">{{ $vehicle['license_plate'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($vehicle['km'], 0, ',', '.') }} km</flux:table.cell>
                                <flux:table.cell>{{ $vehicle['trips'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:text>Keine Kilometerdaten im gewählten Zeitraum.</flux:text>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="md">Top 5 Fahrer nach Kilometern</flux:heading>

            @if (count($this->statistics['top_drivers_by_km']) > 0)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>#</flux:table.column>
                        <flux:table.column>Fahrer</flux:table.column>
                        <flux:table.column>Kilometer</flux:table.column>
                        <flux:table.column>Fahrten</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->statistics['top_drivers_by_km'] as $index => $driver)
                            <flux:table.row>
                                <flux:table.cell>{{ $index + 1 }}</flux:table.cell>
                                <flux:table.cell class="font-medium">{{ $driver['name'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($driver['km'], 0, ',', '.') }} km</flux:table.cell>
                                <flux:table.cell>{{ $driver['trips'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:text>Keine Kilometerdaten im gewählten Zeitraum.</flux:text>
            @endif
        </flux:card>
    </div>

    @if (count($this->statistics['bookings_by_purpose']) > 0)
        <flux:card class="space-y-4">
            <flux:heading size="md">Buchungen nach Art</flux:heading>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->statistics['bookings_by_purpose'] as $purpose)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text size="sm" class="text-zinc-500">{{ $purpose['label'] }}</flux:text>
                        <div class="mt-1 text-2xl font-bold">{{ number_format($purpose['count'], 0, ',', '.') }}</div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
