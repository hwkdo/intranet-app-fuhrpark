<div>
    <x-intranet-app-fuhrpark::fuhrpark-layout heading="Fuhrpark" subheading="Fahrzeuge buchen">
        <div wire:ignore class="glass-card fuhrpark-calendar-shell p-2">
            <div id="fuhrpark-calendar"></div>
        </div>
    </x-intranet-app-fuhrpark::fuhrpark-layout>

    <style>
        #fuhrpark-calendar .fc {
            --fc-border-color: rgb(208 227 249 / 0.8);
            --fc-page-bg-color: transparent;
            --fc-neutral-bg-color: rgb(208 227 249 / 0.35);
            --fc-today-bg-color: rgb(207 227 248 / 0.45);
            --fc-button-bg-color: rgb(208 227 249 / 0.8);
            --fc-button-border-color: rgb(208 227 249);
            --fc-button-text-color: rgb(30 41 59);
            --fc-button-hover-bg-color: rgb(207 227 248);
            --fc-button-hover-border-color: rgb(7 48 112 / 0.25);
            --fc-button-active-bg-color: rgb(7 48 112);
            --fc-button-active-border-color: rgb(7 48 112);
            --fc-button-active-text-color: rgb(255 255 255);
        }

        #fuhrpark-calendar .fc .fc-col-header-cell-cushion,
        #fuhrpark-calendar .fc .fc-daygrid-day-number,
        #fuhrpark-calendar .fc .fc-toolbar-title {
            color: rgb(30 41 59);
        }

        .dark #fuhrpark-calendar .fc {
            --fc-border-color: rgb(255 255 255 / 0.08);
            --fc-page-bg-color: transparent;
            --fc-neutral-bg-color: rgb(4 33 78 / 0.95);
            --fc-neutral-text-color: rgb(241 245 249);
            --fc-today-bg-color: rgb(69 100 148 / 0.4);
            --fc-button-bg-color: rgb(69 100 148 / 0.9);
            --fc-button-border-color: rgb(255 255 255 / 0.12);
            --fc-button-text-color: rgb(241 245 249);
            --fc-button-hover-bg-color: rgb(69 100 148);
            --fc-button-hover-border-color: rgb(255 255 255 / 0.2);
            --fc-button-active-bg-color: rgb(4 33 78);
            --fc-button-active-border-color: rgb(255 255 255 / 0.15);
            --fc-button-active-text-color: rgb(255 255 255);
        }

        .dark #fuhrpark-calendar .fc .fc-col-header-cell {
            background-color: rgb(4 33 78 / 0.95);
        }

        .dark .glass-card.fuhrpark-calendar-shell .fc table thead th,
        .dark .glass-card.fuhrpark-calendar-shell .fc .fc-col-header-cell,
        .dark .glass-card.fuhrpark-calendar-shell .fc .fc-col-header-cell-cushion,
        .dark .glass-card.fuhrpark-calendar-shell .fc .fc-col-header-cell a {
            background-color: rgb(4 33 78 / 0.95) !important;
            color: rgb(241 245 249) !important;
        }

        .dark #fuhrpark-calendar .fc .fc-col-header-cell-cushion,
        .dark #fuhrpark-calendar .fc .fc-daygrid-day-number,
        .dark #fuhrpark-calendar .fc .fc-toolbar-title {
            color: rgb(241 245 249);
        }

        .dark #fuhrpark-calendar .fc .fc-daygrid-day-frame {
            background-color: rgb(7 48 112 / 0.55);
        }

        .dark #fuhrpark-calendar .fc .fc-scrollgrid,
        .dark #fuhrpark-calendar .fc .fc-scrollgrid-section > td,
        .dark #fuhrpark-calendar .fc .fc-scrollgrid-section > th,
        .dark #fuhrpark-calendar .fc .fc-scrollgrid-section-body > td {
            background-color: transparent;
        }

        .fuhrpark-calendar-event--own {
            background-color: #2563eb !important;
            border-color: #1d4ed8 !important;
        }

        .fuhrpark-calendar-event--other {
            background-color: #a1a1aa !important;
            border-color: #71717a !important;
        }

        .fuhrpark-calendar-event--lock {
            background-color: #dc2626 !important;
            border-color: #b91c1c !important;
            color: #ffffff !important;
        }

        .fuhrpark-calendar-event--lock .fc-event-title,
        .fuhrpark-calendar-event--lock .fc-event-time {
            color: #ffffff !important;
        }

        .dark .fuhrpark-calendar-event--own {
            background-color: #3b82f6 !important;
            border-color: #2563eb !important;
        }

        .dark .fuhrpark-calendar-event--other {
            background-color: rgb(69 100 148 / 0.95) !important;
            border-color: rgb(4 33 78) !important;
        }

        .dark .fuhrpark-calendar-event--lock {
            background-color: #ef4444 !important;
            border-color: #dc2626 !important;
            color: #ffffff !important;
        }

        .dark .fuhrpark-calendar-event--lock .fc-event-title,
        .dark .fuhrpark-calendar-event--lock .fc-event-time {
            color: #ffffff !important;
        }
    </style>

    <flux:modal wire:model.self="showBookModal" class="md:w-lg">
        <flux:heading size="lg">Fahrzeug buchen</flux:heading>
        <div class="mt-4 space-y-4">
            <flux:heading size="sm">Von</flux:heading>
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model.live="bookStartDate" type="date" label="Datum" />
                <flux:input wire:model.live="bookStartTime" type="time" label="Uhrzeit" />
            </div>
            <flux:heading size="sm">Bis</flux:heading>
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model.live="bookEndDate" type="date" label="Datum" />
                <flux:input wire:model.live="bookEndTime" type="time" label="Uhrzeit" />
            </div>

            <flux:select
                wire:model.live="bookStandortId"
                variant="listbox"
                searchable
                label="Fahrzeugstandort"
                placeholder="Fahrzeugstandort wählen…"
            >
                @foreach ($this->bookVehicleStandortOptions as $standort)
                    <flux:select.option :value="$standort->id">{{ $standort->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="bookCategoryId" variant="listbox" label="Fahrzeugkategorie" placeholder="Kategorie wählen">
                @foreach ($this->bookCategoryOptions as $option)
                    <flux:select.option
                        :value="$option->category->id"
                        :disabled="! $option->isAvailable"
                    >
                        {{ $option->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->bookRequiresElectricRoute)
                <flux:callout>
                    Hinweis E-Fahrzeug mit begrenzter Reichweite: Tragen Sie bitte die Gesamt-km Ihrer geplanten Route ein.
                </flux:callout>
                <flux:input
                    wire:model.live="bookElectricRouteKm"
                    type="number"
                    min="1"
                    label="Strecke in km"
                    required
                />
                @if ($this->electricRouteExceedsCategoryRange)
                    <flux:callout variant="danger">
                        Die Reichweite dieser Fahrzeugkategorie beträgt {{ $this->selectedBookCategory?->averageElectricRangeKm() }} km und reicht somit für die voraussichtliche Strecke nicht aus.
                    </flux:callout>
                    @if (! $bookElectricRangeAcknowledged)
                        <flux:button wire:click="acknowledgeElectricRangeLimit" class="h-auto! whitespace-normal py-3">
                            <span class="block text-center leading-snug">
                                Ich bin mir der Reichweitenbeschränkung bewusst und möchte<br>
                                trotzdem das Fahrzeug buchen
                            </span>
                        </flux:button>
                    @endif
                @endif
            @endif

            @if (! $bookStandortId)
                <flux:callout variant="warning">Bitte einen Fahrzeugstandort wählen.</flux:callout>
            @elseif ($this->hasValidBookPeriod() && ! $this->hasAvailableBookCategory())
                <flux:callout variant="warning">Keine Kategorie im gewählten Zeitraum verfügbar.</flux:callout>
            @endif

            @if ($this->canShowBookForm())
                <flux:select wire:model.live="bookDriverId" variant="listbox" label="Fahrer" placeholder="Fahrer wählen">
                    @foreach ($this->bookDriverOptions as $driver)
                        <flux:select.option :value="$driver->id">
                            {{ trim($driver->nachname.', '.$driver->vorname) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                @if ($bookCategoryId && $this->bookCategoryRequiresLicense && ! $this->bookingDriverMeetsLicenseRequirement)
                    <flux:callout variant="danger">
                        @if ($this->isBookingForSelf)
                            Sie können ohne gültigen Führerschein keine Fahrzeuge in dieser Kategorie für sich selbst buchen. Wählen Sie eine andere Kategorie, einen anderen Fahrer oder wenden Sie sich an die Personalabteilung.
                        @else
                            Der gewählte Fahrer hat keinen gültigen Führerschein für diese Kategorie.
                        @endif
                    </flux:callout>
                @endif

                <flux:input wire:model.live="bookDescription" label="Zweck" />
                <flux:checkbox wire:model="bookIsCommute" label="Arbeitsfahrt" />
                <flux:checkbox wire:model="bookSyncToCalendar" label="Kalendereintrag" />
                @if ($this->canSubmitBooking())
                    <flux:button
                        variant="primary"
                        wire:click="createBooking"
                        :disabled="$this->bookRequiresElectricRoute && ! $bookElectricRouteKm"
                    >
                        Buchen
                    </flux:button>
                @endif
            @endif
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showDetailModal" class="md:w-lg">
        @if ($this->selectedBooking)
            <flux:heading size="lg">{{ $this->selectedBooking->vehicle->license_plate }}</flux:heading>
            <flux:text class="mt-2">{{ $this->selectedBooking->description }}</flux:text>
            <flux:text class="mt-1 text-sm text-zinc-500">
                @if ($this->selectedBooking->starts_at->isSameDay($this->selectedBooking->ends_at))
                    {{ $this->selectedBooking->starts_at->format('d.m.Y H:i') }} – {{ $this->selectedBooking->ends_at->format('H:i') }}
                @else
                    {{ $this->selectedBooking->starts_at->format('d.m.Y H:i') }} – {{ $this->selectedBooking->ends_at->format('d.m.Y H:i') }}
                @endif
            </flux:text>
            <flux:text class="mt-1 text-sm">Fahrer: {{ $this->selectedBooking->driver->name ?? '-' }}</flux:text>
            <div class="mt-4 flex gap-2">
                @can('update', $this->selectedBooking)
                    <flux:button wire:click="startReschedule">Umbuchen</flux:button>
                @endcan
                @if ($this->canCancelSelectedBooking)
                    <flux:button variant="danger" wire:click="openCancelModal">Löschen</flux:button>
                @endif
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model.self="showCancelModal" class="md:w-lg">
        <flux:heading size="lg">Buchung löschen</flux:heading>

        @if ($this->cancelRequiresReason)
            <flux:text class="mt-2 text-zinc-500">
                Die Buchung ist überfällig. Bitte geben Sie eine Begründung an. Die Fuhrpark-Administration wird informiert.
            </flux:text>
            <flux:textarea wire:model="cancelReason" label="Begründung" class="mt-4" />
        @else
            <flux:text class="mt-4">
                Möchten Sie die Buchung wirklich löschen?
            </flux:text>
        @endif

        <flux:button class="mt-4" variant="danger" wire:click="confirmCancelBooking">Löschen bestätigen</flux:button>
    </flux:modal>

    <flux:modal wire:model.self="showRescheduleModal" class="md:w-lg">
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

    @assets
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    @endassets

    @script
        <script>
            let fuhrparkCalendar = null;

            function initFuhrparkCalendar() {
                const calendarEl = document.getElementById('fuhrpark-calendar');

                if (! calendarEl) {
                    setTimeout(initFuhrparkCalendar, 50);

                    return;
                }

                if (calendarEl.dataset.initialized === '1') {
                    return;
                }

                if (typeof FullCalendar === 'undefined') {
                    setTimeout(initFuhrparkCalendar, 50);

                    return;
                }

                calendarEl.dataset.initialized = '1';

                fuhrparkCalendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    locale: 'de',
                    height: 'auto',
                    hiddenDays: [0, 6],
                    events: (info) => $wire.calendarEvents(info.startStr, info.endStr),
                    dateClick: (info) => {
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        const clicked = new Date(info.dateStr + 'T00:00:00');

                        if (clicked < today) {
                            return;
                        }

                        $wire.openBookModal(info.dateStr);
                    },
                    eventClick: (info) => {
                        info.jsEvent.preventDefault();
                        $wire.openDetailModal(parseInt(info.event.id, 10));
                    },
                });

                fuhrparkCalendar.render();
            }

            initFuhrparkCalendar();

            $wire.on('fuhrpark-calendar-refresh', () => {
                fuhrparkCalendar?.refetchEvents();
            });
        </script>
    @endscript
</div>
