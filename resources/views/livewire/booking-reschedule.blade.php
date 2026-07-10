<div>
    <flux:modal wire:model.self="showModal" class="md:w-lg">
        <flux:heading size="lg">Umbuchen</flux:heading>
        <div class="mt-4 space-y-4">
            <flux:heading size="sm">Von</flux:heading>
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model.live="rescheduleStartDate" type="date" label="Datum" />
                <flux:input wire:model.live="rescheduleStartTime" type="time" label="Uhrzeit" />
            </div>
            <flux:heading size="sm">Bis</flux:heading>
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model.live="rescheduleEndDate" type="date" label="Datum" />
                <flux:input wire:model.live="rescheduleEndTime" type="time" label="Uhrzeit" />
            </div>
            <flux:button variant="primary" wire:click="checkRescheduleAvailability">Verfügbarkeit prüfen</flux:button>

            @if ($rescheduleChecked)
                @if ($this->rescheduleAvailability->noneAvailable)
                    <flux:callout variant="warning">
                        Kein freies Fahrzeug in keiner Kategorie im gewählten Zeitraum verfügbar.
                    </flux:callout>
                @else
                    @if ($this->rescheduleAvailability->hasSameCategoryAlternatives())
                        @if ($this->canSelectRescheduleVehicle)
                            <flux:checkbox
                                wire:model.live="reschedulePreferSameVehicle"
                                label="Gleiches Fahrzeug bevorzugen"
                            />
                            <flux:text>Freie Fahrzeuge in Ihrer Kategorie:</flux:text>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->rescheduleAvailability->sameCategory as $vehicle)
                                    <flux:button
                                        wire:key="reschedule-same-{{ $vehicle->id }}"
                                        wire:click="selectRescheduleVehicle({{ $vehicle->id }})"
                                        :variant="$rescheduleVehicleId === $vehicle->id ? 'primary' : 'ghost'"
                                    >
                                        {{ $vehicle->license_plate }}
                                        @if ($this->selectedBooking && $vehicle->id === $this->selectedBooking->vehicle_id)
                                            (bisheriges Fahrzeug)
                                        @endif
                                    </flux:button>
                                @endforeach
                            </div>
                        @else
                            <flux:callout>
                                In Ihrer Kategorie ist ein Fahrzeug verfügbar. Es wird automatisch das beste Fahrzeug zugewiesen.
                            </flux:callout>
                        @endif
                    @elseif (! $this->canSelectRescheduleVehicle)
                        <flux:callout>
                            In Ihrer Kategorie ist im gewählten Zeitraum kein Fahrzeug frei. Bitte wählen Sie eine andere Kategorie.
                        </flux:callout>
                    @endif

                    @if (
                        $this->rescheduleAvailability->hasOtherCategoryAlternatives()
                        && ($this->canSelectRescheduleVehicle || ! $this->rescheduleAvailability->hasSameCategoryAlternatives())
                    )
                        @if ($this->canSelectRescheduleVehicle && ! $this->rescheduleAvailability->hasSameCategoryAlternatives())
                            <flux:callout>
                                In Ihrer Kategorie ist im gewählten Zeitraum kein Fahrzeug frei. Bitte wählen Sie eine andere Kategorie und ein konkretes Fahrzeug.
                            </flux:callout>
                        @endif

                        <flux:text>
                            @if ($this->canSelectRescheduleVehicle && $this->rescheduleAvailability->hasSameCategoryAlternatives())
                                Weitere verfügbare Kategorien:
                            @else
                                Verfügbare Kategorien:
                            @endif
                        </flux:text>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->rescheduleAvailability->otherCategories as $group)
                                <flux:button
                                    wire:key="reschedule-cat-{{ $group->category->id }}"
                                    wire:click="selectRescheduleOtherCategory({{ $group->category->id }})"
                                    :variant="$this->canSelectRescheduleVehicle && $rescheduleOtherCategoryId === $group->category->id ? 'primary' : 'ghost'"
                                    wire:loading.attr="disabled"
                                    wire:target="selectRescheduleOtherCategory"
                                >
                                    {{ $group->category->name }}
                                    ({{ $group->vehicles->count() }} frei)
                                </flux:button>
                            @endforeach
                        </div>

                        @if ($this->canSelectRescheduleVehicle && $rescheduleOtherCategoryId && $this->rescheduleOtherCategoryVehicles->isNotEmpty())
                            <flux:text>Freie Fahrzeuge in dieser Kategorie:</flux:text>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->rescheduleOtherCategoryVehicles as $vehicle)
                                    <flux:button
                                        wire:key="reschedule-other-{{ $vehicle->id }}"
                                        wire:click="selectRescheduleVehicle({{ $vehicle->id }})"
                                        :variant="$rescheduleVehicleId === $vehicle->id ? 'primary' : 'ghost'"
                                    >
                                        {{ $vehicle->license_plate }}
                                    </flux:button>
                                @endforeach
                            </div>
                        @endif
                    @endif

                    @if (
                        ($this->canSelectRescheduleVehicle && $rescheduleVehicleId)
                        || (! $this->canSelectRescheduleVehicle && $rescheduleCategoryId && $this->rescheduleAvailability->hasSameCategoryAlternatives())
                    )
                        <flux:button variant="primary" wire:click="confirmReschedule">Umbuchen bestätigen</flux:button>
                    @endif
                @endif
            @endif
        </div>
    </flux:modal>
</div>
