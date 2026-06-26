<?php

declare(strict_types=1);

use Carbon\Carbon;
use Flux\Flux;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Hwkdo\IntranetAppFuhrpark\Models\DriverLicenseControl;
use Hwkdo\IntranetAppFuhrpark\Services\DriverLicenseControlService;
use Hwkdo\IntranetAppFuhrpark\Support\FuhrparkModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Fuhrpark - Führerscheine')] class extends Component
{
    use WithFileUploads;

    public string $search = '';

    public ?int $initialUserId = null;

    public bool $showControlModal = false;

    public bool $showDetailModal = false;

    public ?int $controlLicenseId = null;

    public ?int $controlUserId = null;

    public ?int $detailControlId = null;

    public string $restrictedUntil = '';

    public string $note = '';

    public $scan = null;

    public function mount(): void
    {
        $this->authorize('viewAny', DriverLicense::class);
    }

    #[Computed]
    public function licenses(): Collection
    {
        $userModel = FuhrparkModels::user();

        return DriverLicense::query()
            ->with(['user', 'latestControl.inspectedBy'])
            ->whereHas('user', function ($query) use ($userModel): void {
                if (method_exists($userModel, 'scopeAktiv')) {
                    $query->aktiv();
                } else {
                    $query->where('active', true);
                }
            })
            ->get()
            ->filter(function (DriverLicense $license): bool {
                if ($this->search === '') {
                    return true;
                }

                $name = $license->user->name ?? '';

                return str_contains(mb_strtolower($name), mb_strtolower($this->search));
            })
            ->sortBy(fn (DriverLicense $license): string => $license->user->nachname ?? $license->user->name ?? '');
    }

    #[Computed]
    public function usersWithoutLicense(): Collection
    {
        $query = FuhrparkModels::userQuery();

        if (method_exists(FuhrparkModels::user(), 'scopeAktiv')) {
            $query->aktiv();
        } else {
            $query->where('active', true);
        }

        return $query
            ->whereDoesntHave('driverLicense')
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();
    }

    #[Computed]
    public function detailControl(): ?DriverLicenseControl
    {
        if (! $this->detailControlId) {
            return null;
        }

        return DriverLicenseControl::query()
            ->with(['driverLicense.user', 'inspectedBy'])
            ->find($this->detailControlId);
    }

    public function updatedSearch(): void
    {
        unset($this->licenses);
    }

    public function extendOneYearInitial(): void
    {
        $this->authorize('manage', DriverLicense::class);

        $this->validate([
            'initialUserId' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = FuhrparkModels::userQuery()->findOrFail($this->initialUserId);

        if ($user->driverLicense) {
            Flux::toast(text: 'Für diesen Benutzer existiert bereits ein Führerschein.', variant: 'danger');

            return;
        }

        app(DriverLicenseControlService::class)->recordInitialControl(
            user: $user,
            inspector: Auth::user(),
        );

        $this->initialUserId = null;
        unset($this->licenses, $this->usersWithoutLicense);

        Flux::toast(text: 'Erstkontrolle gespeichert (+1 Jahr).', variant: 'success');
    }

    public function extendOneYear(int $licenseId): void
    {
        $this->authorize('manage', DriverLicense::class);

        $license = DriverLicense::query()->findOrFail($licenseId);

        app(DriverLicenseControlService::class)->extendOneYear($license, Auth::user());

        unset($this->licenses);

        Flux::toast(text: 'Führerschein um 1 Jahr verlängert.', variant: 'success');
    }

    public function openControlModalForInitial(): void
    {
        $this->authorize('manage', DriverLicense::class);

        $this->validate([
            'initialUserId' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->controlLicenseId = null;
        $this->controlUserId = $this->initialUserId;
        $this->resetControlForm();
        $this->showControlModal = true;
    }

    public function openControlModalForLicense(int $licenseId): void
    {
        $this->authorize('manage', DriverLicense::class);

        $license = DriverLicense::query()->findOrFail($licenseId);

        $this->controlLicenseId = $license->id;
        $this->controlUserId = $license->user_id;
        $this->resetControlForm();
        $this->showControlModal = true;
    }

    public function openDetailModal(int $controlId): void
    {
        $this->authorize('viewAny', DriverLicense::class);

        $this->detailControlId = $controlId;
        $this->showDetailModal = true;
        unset($this->detailControl);
    }

    public function closeControlModal(): void
    {
        $this->showControlModal = false;
        $this->controlLicenseId = null;
        $this->controlUserId = null;
        $this->resetControlForm();
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->detailControlId = null;
        unset($this->detailControl);
    }

    public function saveControl(): void
    {
        $this->authorize('manage', DriverLicense::class);

        $this->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'restrictedUntil' => ['nullable', 'date'],
            'scan' => ['nullable', 'file', 'max:10240'],
        ]);

        $restrictedUntil = $this->restrictedUntil !== ''
            ? Carbon::parse($this->restrictedUntil)
            : null;

        $service = app(DriverLicenseControlService::class);
        $inspector = Auth::user();

        if ($this->controlLicenseId) {
            $license = DriverLicense::query()->findOrFail($this->controlLicenseId);
            $service->recordFollowUpControl(
                license: $license,
                inspector: $inspector,
                restrictedUntil: $restrictedUntil,
                note: $this->note !== '' ? $this->note : null,
                file: $this->scan,
            );
        } else {
            $user = FuhrparkModels::userQuery()->findOrFail($this->controlUserId);
            $service->recordInitialControl(
                user: $user,
                inspector: $inspector,
                restrictedUntil: $restrictedUntil,
                note: $this->note !== '' ? $this->note : null,
                file: $this->scan,
            );
            $this->initialUserId = null;
        }

        $this->closeControlModal();
        unset($this->licenses, $this->usersWithoutLicense);

        Flux::toast(text: 'Kontrolle gespeichert.', variant: 'success');
    }

    private function resetControlForm(): void
    {
        $this->restrictedUntil = '';
        $this->note = '';
        $this->scan = null;
        $this->resetValidation();
    }
}; ?>

<x-intranet-app-fuhrpark::fuhrpark-layout heading="Führerscheine" subheading="Kontrollen und Verlängerungen verwalten">
    <div class="space-y-6">
        <flux:card class="glass-card">
            <flux:heading size="lg">Neue Erstkontrolle</flux:heading>
            <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-end">
                <flux:field class="flex-1">
                    <flux:label>Mitarbeiter ohne Führerschein</flux:label>
                    <flux:select wire:model="initialUserId" variant="listbox" searchable placeholder="Benutzer auswählen…">
                        @foreach ($this->usersWithoutLicense as $user)
                            <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <div class="flex gap-2">
                    <flux:button wire:click="extendOneYearInitial" wire:loading.attr="disabled">
                        +1 Jahr
                    </flux:button>
                    <flux:button variant="primary" wire:click="openControlModalForInitial" wire:loading.attr="disabled">
                        +Kontrolle
                    </flux:button>
                </div>
            </div>
        </flux:card>

        <flux:card class="glass-card">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <flux:heading size="lg">Führerscheine</flux:heading>
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Nach Name suchen…" class="sm:max-w-xs" />
            </div>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Letzte Kontrolle</flux:table.column>
                    <flux:table.column>Gültig bis</flux:table.column>
                    <flux:table.column>Befristung</flux:table.column>
                    <flux:table.column class="text-right">Aktionen</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->licenses as $license)
                        <flux:table.row wire:key="license-{{ $license->id }}">
                            <flux:table.cell>{{ $license->user->name }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($license->latestControl)
                                    <flux:link href="#" wire:click.prevent="openDetailModal({{ $license->latestControl->id }})">
                                        {{ $license->latestControl->created_at->format('d.m.Y') }}
                                    </flux:link>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $license->valid_until->format('d.m.Y') }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $license->restricted_until?->format('d.m.Y') ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" wire:click="extendOneYear({{ $license->id }})" wire:loading.attr="disabled">
                                        +1 Jahr
                                    </flux:button>
                                    <flux:button size="sm" variant="primary" wire:click="openControlModalForLicense({{ $license->id }})">
                                        +Kontrolle
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">
                                <flux:text>Keine Führerscheine gefunden.</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    <flux:modal wire:model="showControlModal" class="md:w-lg">
        <flux:heading size="lg">
            {{ $controlLicenseId ? 'Folgekontrolle' : 'Erstkontrolle' }}
        </flux:heading>

        <form wire:submit="saveControl" class="mt-4 space-y-4">
            <flux:field>
                <flux:label>Befristung (optional)</flux:label>
                <flux:input type="date" wire:model="restrictedUntil" />
            </flux:field>

            <flux:field>
                <flux:label>Bemerkung</flux:label>
                <flux:textarea wire:model="note" rows="3" />
            </flux:field>

            <flux:field>
                <flux:label>Scan / Anhang</flux:label>
                <flux:input type="file" wire:model="scan" />
                <flux:error name="scan" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeControlModal">Abbrechen</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Speichern</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDetailModal" class="md:w-lg">
        @if ($this->detailControl)
            <flux:heading size="lg">Führerscheinkontrolle</flux:heading>

            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="font-medium text-zinc-500 dark:text-zinc-400">Datum</dt>
                    <dd>{{ $this->detailControl->created_at->format('d.m.Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-zinc-500 dark:text-zinc-400">Kontrolleur</dt>
                    <dd>{{ $this->detailControl->inspectedBy->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-zinc-500 dark:text-zinc-400">Kontrollierte/r</dt>
                    <dd>{{ $this->detailControl->driverLicense->user->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-zinc-500 dark:text-zinc-400">Bemerkung</dt>
                    <dd>{{ $this->detailControl->note ?? '—' }}</dd>
                </div>
                @if ($this->detailControl->hasFile())
                    <div>
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">Anhang</dt>
                        <dd>
                            <flux:link href="{{ route('apps.fuhrpark.driver-license-controls.download', $this->detailControl) }}" target="_blank">
                                {{ $this->detailControl->file_name }}
                            </flux:link>
                        </dd>
                    </div>
                @endif
            </dl>

            <div class="mt-6 flex justify-end">
                <flux:button wire:click="closeDetailModal">Schließen</flux:button>
            </div>
        @endif
    </flux:modal>
</x-intranet-app-fuhrpark::fuhrpark-layout>
