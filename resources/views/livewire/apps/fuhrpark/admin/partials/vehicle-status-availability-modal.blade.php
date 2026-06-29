<flux:modal wire:model="showAvailabilityModal" class="md:w-lg">
    <flux:heading size="lg">
        {{ $this->statusVehicle?->hasAvailabilityRestriction() ? 'Verfügbarkeit ändern' : 'Verfügbarkeit festlegen' }}
    </flux:heading>

    <div class="mt-4 space-y-4">
        <flux:text>Leer lassen = unbegrenzt verfügbar.</flux:text>

        @if ($this->statusVehicle?->hasAvailabilityRestriction())
            <flux:callout variant="warning">
                <flux:callout.text>
                    Aktuelle Beschränkung: {{ $this->statusVehicle->availabilityLabel() }}
                </flux:callout.text>
            </flux:callout>
            <flux:button variant="ghost" wire:click="clearAvailability">
                Beschränkung aufheben
            </flux:button>
        @endif

        @if ($availabilityStep === 1)
            <div class="space-y-3">
                <flux:input wire:model.live="availableFrom" type="datetime-local" label="Verfügbar ab" />
                <flux:input wire:model.live="availableUntil" type="datetime-local" label="Verfügbar bis" />
                <flux:button variant="primary" wire:click="checkAvailabilityConflicts">Prüfen</flux:button>
            </div>
        @else
            <div class="space-y-4">
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
</flux:modal>
