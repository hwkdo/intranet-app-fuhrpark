<?php

use Hwkdo\IntranetAppFuhrpark\Models\Project;
use function Livewire\Volt\{computed, state};

state([
    'showModal' => false,
    'editingId' => null,
    'name' => '',
    'active' => true,
]);

$projects = computed(fn () => Project::query()->orderBy('name')->get());

$save = function (): void {
    $this->validate(['name' => 'required|string|max:150']);

    Project::query()->updateOrCreate(
        ['id' => $this->editingId],
        ['name' => $this->name, 'active' => (bool) $this->active],
    );

    $this->showModal = false;
};

$openCreate = function (): void {
    $this->reset(['editingId', 'name', 'active']);
    $this->active = true;
    $this->showModal = true;
};

$openEdit = function (int $id): void {
    $project = Project::query()->findOrFail($id);
    $this->editingId = $id;
    $this->name = $project->name;
    $this->active = $project->active;
    $this->showModal = true;
};

?>

<div>
    <flux:button wire:click="openCreate" class="mb-4">Neues Projekt</flux:button>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Aktiv</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->projects as $project)
                <flux:table.row>
                    <flux:table.cell>{{ $project->name }}</flux:table.cell>
                    <flux:table.cell>{{ $project->active ? 'Ja' : 'Nein' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" wire:click="openEdit({{ $project->id }})">Bearbeiten</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showModal" class="md:w-lg">
        <flux:heading size="lg">Projekt</flux:heading>
        <div class="mt-4 space-y-4">
            <flux:input wire:model="name" label="Name" />
            <flux:checkbox wire:model.boolean="active" label="Aktiv" />
            <flux:button variant="primary" wire:click="save">Speichern</flux:button>
        </div>
    </flux:modal>
</div>
