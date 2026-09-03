    <flux:card class="mb-6">
        <flux:field class="max-w-md">
            <flux:label>Fahrzeugstandort</flux:label>
            <flux:select
                wire:model.live="deskStandortId"
                variant="listbox"
                searchable
                placeholder="Fahrzeugstandort wählen…"
            >
                @foreach ($this->deskVehicleStandorte as $standort)
                    <flux:select.option value="{{ $standort->id }}">{{ $standort->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
    </flux:card>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card>
            <flux:heading size="lg">Erwartete Abholungen</flux:heading>
            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Fahrzeug</flux:table.column>
                    <flux:table.column>Fahrer</flux:table.column>
                    <flux:table.column>Zeit</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->expectedHandouts as $booking)
                        <flux:table.row>
                            <flux:table.cell>{{ $booking->vehicle->license_plate }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->driver->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->starts_at->format('H:i') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button size="sm" wire:click="openHandout({{ $booking->id }})">Ausgeben</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:card>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <flux:heading size="lg">
                    {{ $showAllVehiclesOnRoad ? 'Fahrzeuge unterwegs' : 'Erwartete Rückgaben' }}
                </flux:heading>

                <flux:field class="shrink-0">
                    <flux:label>Anzeigemodus</flux:label>
                    <div class="flex items-center gap-3">
                        <flux:text class="text-sm {{ ! $showAllVehiclesOnRoad ? 'font-medium text-zinc-900 dark:text-white' : 'text-zinc-500' }}">
                            Nur Erwartete Fahrzeuge
                        </flux:text>
                        <flux:switch wire:model.live="showAllVehiclesOnRoad" />
                        <flux:text class="text-sm {{ $showAllVehiclesOnRoad ? 'font-medium text-zinc-900 dark:text-white' : 'text-zinc-500' }}">
                            Alle Fahrzeuge
                        </flux:text>
                    </div>
                </flux:field>
            </div>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Fahrzeug</flux:table.column>
                    <flux:table.column>Fahrer</flux:table.column>
                    @if ($showAllVehiclesOnRoad)
                        <flux:table.column>Rückgabe erwartet</flux:table.column>
                    @endif
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->expectedReturns as $booking)
                        <flux:table.row>
                            <flux:table.cell>{{ $booking->vehicle->license_plate }}</flux:table.cell>
                            <flux:table.cell>{{ $booking->driver->name ?? '-' }}</flux:table.cell>
                            @if ($showAllVehiclesOnRoad)
                                <flux:table.cell>{{ $booking->ends_at->format('d.m.Y H:i') }}</flux:table.cell>
                            @endif
                            <flux:table.cell>
                                <flux:button size="sm" wire:click="openReturn({{ $booking->id }})">Rückgabe</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="{{ $showAllVehiclesOnRoad ? 4 : 3 }}" class="text-zinc-500">
                                {{ $showAllVehiclesOnRoad ? 'Keine Fahrzeuge unterwegs.' : 'Keine Rückgaben für heute erwartet.' }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    <flux:modal wire:model="showHandoutModal" class="md:w-xl">
        <flux:heading size="lg">Fahrzeug ausgeben</flux:heading>
        @if($showHandoutModal && $this->selectedBooking)
            <flux:text class="mt-2">{{ $this->selectedBooking->vehicle->license_plate }}</flux:text>

            @if ($this->handoutBlockedByPredecessor && $this->handoutPredecessor)
                <flux:callout variant="warning" icon="exclamation-triangle" class="mt-4">
                    Diese Buchung kann nicht ausgegeben werden, da die Vorgängerbuchung noch nicht zurückgegeben wurde.
                </flux:callout>

                <div class="mt-4 space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">Vorgängerbuchung</flux:heading>

                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-zinc-500">Kennzeichen</dt>
                            <dd class="font-medium">{{ $this->handoutPredecessor->vehicle->license_plate }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Fahrer</dt>
                            <dd class="font-medium">{{ $this->handoutPredecessor->driver->name ?? '–' }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Zeitraum</dt>
                            <dd class="font-medium">
                                {{ $this->handoutPredecessor->starts_at->format('d.m.Y H:i') }}
                                –
                                {{ $this->handoutPredecessor->ends_at->format('d.m.Y H:i') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Status</dt>
                            <dd class="font-medium">{{ $this->handoutPredecessorStatus?->label() ?? '–' }}</dd>
                        </div>
                    </dl>
                </div>
            @else
                <flux:field class="mt-4">
                    <flux:label>Fahrer</flux:label>
                    <flux:select
                        wire:model.live="handoutDriverId"
                        variant="listbox"
                        searchable
                        placeholder="Fahrer auswählen…"
                    >
                        @foreach ($this->handoutDrivers as $user)
                            <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <div class="mt-4" wire:key="handout-signopad-{{ $handoutDriverId }}">
                    <livewire:signopad.signpad
                        :fields="[]"
                        text-oben="Fahrzeugübergabe {{ $this->selectedBooking->vehicle->license_plate }}"
                        text-unten="{{ $this->selectedHandoutDriverName }}"
                        :key="'signpad-'.$handoutDriverId"
                    />
                </div>

                @if ($signatureData)
                    <flux:callout variant="success" icon="check-circle" class="mt-4">
                        Unterschrift erfasst. Sie können die Ausgabe jetzt bestätigen.
                    </flux:callout>
                @else
                    <flux:text class="mt-4 text-zinc-500">
                        Bitte zuerst die Unterschrift am Signopad erfassen.
                    </flux:text>
                @endif

                <flux:button
                    class="mt-4"
                    variant="primary"
                    wire:click="confirmHandout"
                    :disabled="! $signatureData || ! $handoutDriverId"
                >
                    Ausgabe bestätigen
                </flux:button>
            @endif
        @endif
    </flux:modal>

    <flux:modal wire:model="showReturnModal" class="md:w-lg">
        <flux:heading size="lg">Fahrzeug zurücknehmen</flux:heading>
        @if ($showReturnModal && $this->selectedBooking)
        <div class="mt-4 space-y-4">
            <flux:input wire:model="returnKmEnd" type="number" label="KM Ende" />
            <flux:checkbox wire:model="returnChecklist.key" label="Schlüssel" />
            <flux:checkbox wire:model="returnChecklist.license" label="Führerschein" />
            <flux:checkbox wire:model="returnChecklist.fuel_card" label="Tankkarte" />
            <flux:checkbox wire:model="returnHasDamage" label="Schaden" />
            @if($returnHasDamage)
                <flux:textarea wire:model="returnDamageNote" label="Schadensbeschreibung" />
            @endif

            <div wire:key="return-signopad-{{ $selectedBookingId }}">
                <livewire:signopad.signpad
                    :fields="[]"
                    text-oben="Fahrzeugrückgabe {{ $this->selectedBooking->vehicle->license_plate }}"
                    text-unten="{{ $this->selectedBooking->driver->name ?? '' }}"
                    :key="'return-signpad-'.$selectedBookingId"
                />
            </div>

            @if ($returnSignatureData)
                <flux:callout variant="success" icon="check-circle">
                    Unterschrift erfasst. Sie können die Rückgabe jetzt bestätigen.
                </flux:callout>
            @else
                <flux:text class="text-zinc-500">
                    Bitte zuerst die Unterschrift am Signopad erfassen.
                </flux:text>
            @endif

            <flux:button
                variant="primary"
                wire:click="confirmReturn"
                :disabled="! $returnSignatureData"
            >
                Rückgabe bestätigen
            </flux:button>
        </div>
        @endif
    </flux:modal>
