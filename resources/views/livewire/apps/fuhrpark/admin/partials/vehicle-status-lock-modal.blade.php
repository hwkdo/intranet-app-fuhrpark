<flux:modal wire:model="showLockModal" class="md:w-lg">
    <flux:heading size="lg">
        {{ $this->activeLockBookings->isNotEmpty() ? 'Fahrzeug entsperren' : 'Fahrzeug sperren' }}
    </flux:heading>

    <div class="mt-4 space-y-4">
        @if ($this->activeLockBookings->isNotEmpty())
            <div class="space-y-2">
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

            <flux:separator />
        @endif

        <flux:heading size="sm">Neue Sperre anlegen</flux:heading>

        @if ($lockStep === 1)
            <div class="space-y-3">
                <flux:input wire:model="lockStart" type="datetime-local" label="Sperre von" />
                <flux:input wire:model="lockEnd" type="datetime-local" label="Sperre bis" />
                <flux:input wire:model="lockReason" label="Sperrgrund" />
                <flux:button variant="primary" wire:click="checkLockConflicts">Prüfen</flux:button>
            </div>
        @else
            <div class="space-y-4">
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
</flux:modal>
