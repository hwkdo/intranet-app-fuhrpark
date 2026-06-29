<flux:modal wire:model="showDeactivateModal" class="md:w-lg">
    <flux:heading size="lg">
        {{ $this->statusVehicle?->active ? 'Fahrzeug deaktivieren' : 'Fahrzeug aktivieren' }}
    </flux:heading>

    <div class="mt-4 space-y-4">
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
            <flux:button variant="primary" wire:click="reactivateVehicle">Aktivieren</flux:button>
        @else
            @if ($deactivateStep === 1)
                <div class="space-y-3">
                    <flux:textarea wire:model="inactiveReason" label="Grund für Deaktivierung" />
                    <flux:button variant="primary" wire:click="checkDeactivateConflicts">Prüfen</flux:button>
                </div>
            @else
                <div class="space-y-4">
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
        @endif
    </div>
</flux:modal>
