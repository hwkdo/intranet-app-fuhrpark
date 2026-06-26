<flux:card class="space-y-3" wire:key="vehicle-lock-conflict-{{ $bookingId }}">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <flux:heading size="sm">Buchung #{{ $bookingId }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                {{ $this->booking->starts_at->format('d.m.Y H:i') }}
                –
                {{ $this->booking->ends_at->format('d.m.Y H:i') }}
                @if ($this->booking->driver)
                    · {{ $this->booking->driver->name ?? 'Fahrer #'.$this->booking->driver_id }}
                @endif
            </flux:text>
            @if ($this->booking->description)
                <flux:text class="text-sm">{{ $this->booking->description }}</flux:text>
            @endif
        </div>
    </div>

    @if ($resolved)
        <flux:callout variant="success">
            @if ($resolution === 'rescheduled')
                Umgebucht auf {{ $this->targetVehicle?->license_plate ?? 'anderes Fahrzeug' }}.
            @else
                Buchung wurde gelöscht.
            @endif
        </flux:callout>
    @else
        @if ($this->hasNoReschedulableCategories)
            <flux:callout variant="warning">
                Keine Kategorie zum Umbuchen verfügbar. Die Buchung kann nur gelöscht werden.
                Fahrer und Buchender werden per E-Mail informiert.
            </flux:callout>
            <flux:button variant="danger" wire:click="deleteBooking">Löschen</flux:button>
        @else
            <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                <flux:select
                    wire:model.live="categoryId"
                    variant="listbox"
                    label="Kategorie für Umbuchung"
                    placeholder="Kategorie wählen"
                >
                    @foreach ($this->categoryBookingOptions as $option)
                        <flux:select.option
                            :value="$option->category->id"
                            :disabled="! $option->isAvailable"
                        >
                            {{ $option->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button wire:click="checkReschedule" wire:loading.attr="disabled">Prüfen</flux:button>
            </div>

            @if ($checked && $targetVehicleId)
                <flux:callout variant="success">
                    Umbuchbar auf {{ $this->targetVehicle->license_plate }}.
                    <flux:button class="mt-2" size="sm" variant="primary" wire:click="confirmReschedule">
                        Umbuchen
                    </flux:button>
                </flux:callout>
            @elseif ($checked && $notReschedulable)
                <flux:callout variant="danger">
                    Nicht umbuchbar.
                    <flux:button class="mt-2" size="sm" variant="danger" wire:click="deleteBooking">
                        Löschen
                    </flux:button>
                </flux:callout>
            @endif
        @endif
    @endif
</flux:card>
