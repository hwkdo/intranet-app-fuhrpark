<flux:modal wire:model="showWorkshopModal" class="md:w-lg">
    <flux:heading size="lg">Werkstattfahrt</flux:heading>

    <div class="mt-4 space-y-4">
        @if ($this->activeWorkshopBooking)
            <flux:callout variant="info">
                <flux:callout.heading>Werkstattfahrt läuft</flux:callout.heading>
                <flux:callout.text>
                    Fahrer: {{ $this->activeWorkshopBooking->driver?->name ?? '–' }}
                    <br>
                    Von {{ $this->activeWorkshopBooking->starts_at->format('d.m.Y H:i') }}
                    bis {{ $this->activeWorkshopBooking->ends_at->format('d.m.Y H:i') }}
                </flux:callout.text>
            </flux:callout>
        @else
            <flux:text class="text-sm text-zinc-500">
                Neue Werkstattfahrt für {{ $this->statusVehicle?->license_plate }} anlegen.
            </flux:text>

            <flux:select
                wire:model="workshopDriverId"
                variant="listbox"
                searchable
                label="Fahrer"
                placeholder="Fahrer auswählen…"
            >
                @foreach ($this->workshopDrivers as $user)
                    <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="workshopStart" type="datetime-local" label="Werkstatt von" />
            <flux:input wire:model="workshopEnd" type="datetime-local" label="Werkstatt bis" />
            <flux:button variant="primary" wire:click="createWorkshop">Werkstattfahrt anlegen</flux:button>
        @endif
    </div>
</flux:modal>
