<?php

use Hwkdo\IntranetAppFuhrpark\Services\DriverLicenseService;
use function Livewire\Volt\{computed, title};

title('Fuhrpark - App-Info');

$driverLicenseService = computed(fn () => app(DriverLicenseService::class));

$license = computed(fn () => $this->driverLicenseService->licenseFor(auth()->user()));

$isValid = computed(fn () => $this->driverLicenseService->isValid(auth()->user()));

$isExpiringSoon = computed(fn () => $this->driverLicenseService->isExpiringSoon(auth()->user()));

$licenseBlocksSelfBooking = computed(fn () => ! app(DriverLicenseService::class)->isValid(auth()->user()));

?>

<x-intranet-app-fuhrpark::fuhrpark-layout heading="App-Info" subheading="Installierte Version und Release-Historie">
    <div class="space-y-6">
        <flux:card class="glass-card">
            <flux:heading size="lg">Führerschein</flux:heading>

            @if ($this->license)
                <flux:table class="mt-4">
                    <flux:table.rows>
                        <flux:table.row>
                            <flux:table.cell class="font-medium w-1/3">Kontrolle Personalwesen gültig bis</flux:table.cell>
                            <flux:table.cell>{{ $this->license->valid_until->format('d.m.Y') }}</flux:table.cell>
                        </flux:table.row>
                        @if ($this->license->restricted_until)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">Amtliche Befristung</flux:table.cell>
                                <flux:table.cell>{{ $this->license->restricted_until->format('d.m.Y') }}</flux:table.cell>
                            </flux:table.row>
                        @endif
                    </flux:table.rows>
                </flux:table>

                @if (! $this->isValid)
                    <flux:callout variant="danger" class="mt-4" icon="exclamation-triangle">
                        Ihr Führerschein ist nicht mehr gültig. Bitte wenden Sie sich an die Abteilung Personalwesen.
                    </flux:callout>
                @elseif ($this->isExpiringSoon)
                    <flux:callout variant="warning" class="mt-4" icon="exclamation-triangle">
                        Ihr Führerschein läuft in Kürze ab. Bitte wenden Sie sich an die Abteilung Personalwesen.
                    </flux:callout>
                @endif
            @else
                <flux:callout variant="warning" class="mt-4" icon="exclamation-triangle">
                    Ihr Führerschein wurde noch nicht durch die Abteilung Personalwesen kontrolliert. Bitte setzen Sie sich mit der Abteilung Personalwesen in Verbindung.
                </flux:callout>
            @endif

            @if ($this->licenseBlocksSelfBooking)
                <flux:callout variant="danger" class="mt-4" icon="no-symbol">
                    Sie können ohne gültigen Führerschein keine Fahrzeuge in Kategorien mit Führerscheinpflicht für sich selbst buchen. Kategorien ohne Führerscheinpflicht und Buchungen für andere Fahrer sind weiterhin möglich.
                </flux:callout>
            @endif
        </flux:card>

        @livewire('intranet-app-base::app-info', ['appIdentifier' => 'fuhrpark'])
    </div>
</x-intranet-app-fuhrpark::fuhrpark-layout>
