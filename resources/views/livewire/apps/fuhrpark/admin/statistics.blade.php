<?php

use Hwkdo\IntranetAppFuhrpark\Services\FuhrparkAdminStatisticsService;
use function Livewire\Volt\{computed, state, updated};

state([
    'period' => 'month',
    'statistics' => null,
]);

$periodLabel = computed(function (): string {
    $now = now();

    return match ($this->period) {
        'year' => 'Aktuelles Jahr ('.$now->year.', bis '.$now->translatedFormat('d.m.').')',
        'all' => 'Gesamt',
        default => 'Aktueller Monat ('.$now->translatedFormat('F Y').')',
    };
});

$loadStatistics = function (): void {
    $this->statistics = app(FuhrparkAdminStatisticsService::class)->collect($this->period);
};

updated([
    'period' => function (): void {
        $this->loadStatistics();
    },
]);

?>

<div class="space-y-6" wire:init="loadStatistics">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="lg">Fuhrpark-Statistik</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                <span wire:loading.remove wire:target="period, loadStatistics">{{ $this->periodLabel }}</span>
                <span wire:loading wire:target="period, loadStatistics" class="inline-flex items-center gap-2">
                    <flux:icon.loading variant="micro" />
                    Statistik wird berechnet…
                </span>
            </flux:text>
        </div>

        <flux:select wire:model.live="period" class="w-full sm:w-56" wire:loading.attr="disabled">
            <flux:select.option value="month">Aktueller Monat</flux:select.option>
            <flux:select.option value="year">Aktuelles Jahr</flux:select.option>
            <flux:select.option value="all">Gesamt</flux:select.option>
        </flux:select>
    </div>

    @if ($statistics === null)
        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div class="space-y-2">
                    <flux:skeleton class="h-6 w-40" />
                    <flux:skeleton class="h-4 w-full max-w-2xl" />
                </div>
                <flux:skeleton class="h-20 w-full rounded-lg" />
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach (range(1, 4) as $i)
                        <flux:skeleton class="h-28 w-full rounded-lg" wire:key="util-skeleton-{{ $i }}" />
                    @endforeach
                </div>
                <div class="space-y-3">
                    <flux:skeleton class="h-5 w-44" />
                    @foreach (range(1, 4) as $i)
                        <flux:skeleton class="h-10 w-full rounded-md" wire:key="table-skeleton-{{ $i }}" />
                    @endforeach
                </div>
            </flux:card>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach (range(1, 4) as $i)
                    <flux:skeleton class="h-28 w-full rounded-lg" wire:key="overview-skeleton-{{ $i }}" />
                @endforeach
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach (range(1, 4) as $i)
                    <flux:skeleton class="h-24 w-full rounded-lg" wire:key="top-skeleton-{{ $i }}" />
                @endforeach
            </div>
        </div>
    @else
        <div class="space-y-6" wire:loading.class="pointer-events-none opacity-60" wire:target="period">
            @php
                $utilization = $statistics['utilization'];
                $assessmentColor = match ($utilization['assessment']['status']) {
                    'shortage' => 'red',
                    'surplus' => 'yellow',
                    default => 'green',
                };
            @endphp

            <flux:card class="space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <flux:heading size="md">Flottenauslastung</flux:heading>
                        <flux:text class="mt-1 text-zinc-500">
                            Auslastung nur in den Geschäftszeiten {{ $utilization['business_hours_label'] }}.
                            Inaktive Fahrzeuge und Fahrzeuge außerhalb ihres Verfügbarkeitsfensters im Zeitraum fließen nicht ein.
                        </flux:text>
                    </div>
                </div>

                <flux:callout icon="chart-bar" :color="$assessmentColor">
                    <flux:callout.heading>{{ $utilization['assessment']['label'] }}</flux:callout.heading>
                    <flux:callout.text>{{ $utilization['assessment']['hint'] }}</flux:callout.text>
                </flux:callout>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <flux:card class="flex flex-col gap-1 border-0 shadow-none ring-1 ring-zinc-200 dark:ring-zinc-700">
                        <flux:text size="sm" class="text-zinc-500">Ø Flottenauslastung</flux:text>
                        <div class="text-3xl font-bold">{{ number_format($utilization['average_utilization_percent'], 1, ',', '.') }} %</div>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ number_format($utilization['booked_vehicle_hours'], 1, ',', '.') }} von
                            {{ number_format($utilization['available_vehicle_hours'], 0, ',', '.') }} Std.
                        </flux:text>
                    </flux:card>

                    <flux:card class="flex flex-col gap-1 border-0 shadow-none ring-1 ring-zinc-200 dark:ring-zinc-700">
                        <flux:text size="sm" class="text-zinc-500">Spitzenauslastung</flux:text>
                        <div class="text-3xl font-bold">{{ number_format($utilization['peak_utilization_percent'], 1, ',', '.') }} %</div>
                        <flux:text size="sm" class="text-zinc-500">
                            max. {{ $utilization['peak_concurrent_bookings'] }} von {{ $utilization['fleet_size'] }} Fahrzeugen gleichzeitig
                        </flux:text>
                    </flux:card>

                    <flux:card class="flex flex-col gap-1 border-0 shadow-none ring-1 ring-zinc-200 dark:ring-zinc-700">
                        <flux:text size="sm" class="text-zinc-500">Volllast-Tage</flux:text>
                        <div class="text-3xl font-bold">{{ number_format($utilization['full_capacity_days'], 0, ',', '.') }}</div>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ number_format($utilization['high_demand_days'], 0, ',', '.') }} Tage mit ≥ 80 % Spitzenauslastung
                        </flux:text>
                    </flux:card>

                    <flux:card class="flex flex-col gap-1 border-0 shadow-none ring-1 ring-zinc-200 dark:ring-zinc-700">
                        <flux:text size="sm" class="text-zinc-500">Ungenutzte Fahrzeuge</flux:text>
                        <div class="text-3xl font-bold">{{ number_format($utilization['vehicles_without_bookings'], 0, ',', '.') }}</div>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ number_format($utilization['vehicles_low_utilization'], 0, ',', '.') }} mit &lt; 15 % Auslastung
                        </flux:text>
                    </flux:card>
                </div>

                @if (count($utilization['vehicles']) > 0)
                    <div class="space-y-3">
                        <flux:heading size="sm">Auslastung je Fahrzeug</flux:heading>

                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Kennzeichen</flux:table.column>
                                <flux:table.column>Buchungen</flux:table.column>
                                <flux:table.column>Gebucht</flux:table.column>
                                <flux:table.column>Verfügbar</flux:table.column>
                                <flux:table.column>Auslastung</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($utilization['vehicles'] as $vehicle)
                                    <flux:table.row>
                                        <flux:table.cell class="font-medium">{{ $vehicle['license_plate'] }}</flux:table.cell>
                                        <flux:table.cell>{{ $vehicle['bookings'] }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($vehicle['booked_hours'], 1, ',', '.') }} Std.</flux:table.cell>
                                        <flux:table.cell>{{ number_format($vehicle['available_hours'], 0, ',', '.') }} Std.</flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex items-center gap-2">
                                                <div class="h-2 w-24 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                                    <div
                                                        class="h-full rounded-full {{ $vehicle['utilization_percent'] >= 65 ? 'bg-red-500' : ($vehicle['utilization_percent'] < 15 ? 'bg-amber-400' : 'bg-emerald-500') }}"
                                                        style="width: {{ min(100, $vehicle['utilization_percent']) }}%"
                                                    ></div>
                                                </div>
                                                <span>{{ number_format($vehicle['utilization_percent'], 1, ',', '.') }} %</span>
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif
            </flux:card>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <flux:card class="flex flex-col gap-1">
                    <flux:text size="sm" class="text-zinc-500">Gefahrene Kilometer</flux:text>
                    <div class="text-3xl font-bold">{{ number_format($statistics['overview']['total_km'], 0, ',', '.') }} km</div>
                    <flux:text size="sm" class="text-zinc-500">
                        {{ number_format($statistics['overview']['completed_trips'], 0, ',', '.') }} abgeschlossene Fahrten
                    </flux:text>
                </flux:card>

                <flux:card class="flex flex-col gap-1">
                    <flux:text size="sm" class="text-zinc-500">Ø Kilometer pro Fahrt</flux:text>
                    <div class="text-3xl font-bold">{{ number_format($statistics['overview']['average_km_per_trip'], 1, ',', '.') }} km</div>
                    <flux:text size="sm" class="text-zinc-500">bei erfasstem Kilometerstand</flux:text>
                </flux:card>

                <flux:card class="flex flex-col gap-1">
                    <flux:text size="sm" class="text-zinc-500">Buchungen</flux:text>
                    <div class="text-3xl font-bold">{{ number_format($statistics['overview']['total_bookings'], 0, ',', '.') }}</div>
                    <flux:text size="sm" class="text-zinc-500">
                        {{ number_format($statistics['overview']['commute_bookings'], 0, ',', '.') }} Arbeitsfahrten
                    </flux:text>
                </flux:card>

                <flux:card class="flex flex-col gap-1">
                    <flux:text size="sm" class="text-zinc-500">Aktive Fahrzeuge</flux:text>
                    <div class="text-3xl font-bold">{{ number_format($statistics['overview']['active_vehicles'], 0, ',', '.') }}</div>
                    <flux:text size="sm" class="text-zinc-500">im Fuhrparkbestand</flux:text>
                </flux:card>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <flux:card class="flex flex-col gap-2">
                    <flux:heading size="sm" class="text-zinc-500">Meiste Kilometer (Fahrzeug)</flux:heading>
                    @if ($statistics['top_vehicle_by_km'])
                        <div class="text-2xl font-bold">{{ $statistics['top_vehicle_by_km']['license_plate'] }}</div>
                        <flux:text size="sm">
                            {{ number_format($statistics['top_vehicle_by_km']['km'], 0, ',', '.') }} km
                            · {{ $statistics['top_vehicle_by_km']['trips'] }} Fahrten
                        </flux:text>
                    @else
                        <flux:text>Keine Kilometerdaten im Zeitraum</flux:text>
                    @endif
                </flux:card>

                <flux:card class="flex flex-col gap-2">
                    <flux:heading size="sm" class="text-zinc-500">Meiste Buchungen (Fahrzeug)</flux:heading>
                    @if ($statistics['top_vehicle_by_bookings'])
                        <div class="text-2xl font-bold">{{ $statistics['top_vehicle_by_bookings']['license_plate'] }}</div>
                        <flux:text size="sm">{{ $statistics['top_vehicle_by_bookings']['bookings'] }} Buchungen</flux:text>
                    @else
                        <flux:text>Keine Buchungen im Zeitraum</flux:text>
                    @endif
                </flux:card>

                <flux:card class="flex flex-col gap-2">
                    <flux:heading size="sm" class="text-zinc-500">Meiste Kilometer (Fahrer)</flux:heading>
                    @if ($statistics['top_driver_by_km'])
                        <div class="text-2xl font-bold">{{ $statistics['top_driver_by_km']['name'] }}</div>
                        <flux:text size="sm">
                            {{ number_format($statistics['top_driver_by_km']['km'], 0, ',', '.') }} km
                            · {{ $statistics['top_driver_by_km']['trips'] }} Fahrten
                        </flux:text>
                    @else
                        <flux:text>Keine Kilometerdaten im Zeitraum</flux:text>
                    @endif
                </flux:card>

                <flux:card class="flex flex-col gap-2">
                    <flux:heading size="sm" class="text-zinc-500">Meiste Buchungen (Fahrer)</flux:heading>
                    @if ($statistics['top_driver_by_bookings'])
                        <div class="text-2xl font-bold">{{ $statistics['top_driver_by_bookings']['name'] }}</div>
                        <flux:text size="sm">{{ $statistics['top_driver_by_bookings']['bookings'] }} Buchungen</flux:text>
                    @else
                        <flux:text>Keine Buchungen im Zeitraum</flux:text>
                    @endif
                </flux:card>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:card class="flex flex-col gap-1">
                    <flux:text size="sm" class="text-zinc-500">Ausgaben</flux:text>
                    <div class="text-2xl font-bold">{{ number_format($statistics['overview']['handouts'], 0, ',', '.') }}</div>
                </flux:card>

                <flux:card class="flex flex-col gap-1">
                    <flux:text size="sm" class="text-zinc-500">Rückgaben</flux:text>
                    <div class="text-2xl font-bold">{{ number_format($statistics['overview']['returns'], 0, ',', '.') }}</div>
                </flux:card>

                <flux:card class="flex flex-col gap-1">
                    <flux:text size="sm" class="text-zinc-500">Elektro-Strecke</flux:text>
                    <div class="text-2xl font-bold">{{ number_format($statistics['overview']['electric_route_km'], 0, ',', '.') }} km</div>
                </flux:card>

                <flux:card class="flex flex-col gap-1">
                    <flux:text size="sm" class="text-zinc-500">Verbrenner-Strecke</flux:text>
                    <div class="text-2xl font-bold">{{ number_format($statistics['overview']['combustion_route_km'], 0, ',', '.') }} km</div>
                </flux:card>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <flux:card class="space-y-4">
                    <flux:heading size="md">Top 5 Fahrzeuge nach Kilometern</flux:heading>

                    @if (count($statistics['top_vehicles_by_km']) > 0)
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>#</flux:table.column>
                                <flux:table.column>Kennzeichen</flux:table.column>
                                <flux:table.column>Kilometer</flux:table.column>
                                <flux:table.column>Fahrten</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($statistics['top_vehicles_by_km'] as $index => $vehicle)
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

                    @if (count($statistics['top_drivers_by_km']) > 0)
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>#</flux:table.column>
                                <flux:table.column>Fahrer</flux:table.column>
                                <flux:table.column>Kilometer</flux:table.column>
                                <flux:table.column>Fahrten</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($statistics['top_drivers_by_km'] as $index => $driver)
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

            @if (count($statistics['bookings_by_purpose']) > 0)
                <flux:card class="space-y-4">
                    <flux:heading size="md">Buchungen nach Art</flux:heading>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($statistics['bookings_by_purpose'] as $purpose)
                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                <flux:text size="sm" class="text-zinc-500">{{ $purpose['label'] }}</flux:text>
                                <div class="mt-1 text-2xl font-bold">{{ number_format($purpose['count'], 0, ',', '.') }}</div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif
        </div>
    @endif
</div>
