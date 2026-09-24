<?php

namespace App\Livewire\Connections;

use App\Models\Connection;
use App\Models\System;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Index extends Component
{
    public System $system;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|exists:auth_profiles,id')]
    public ?int $auth_profile_id = null;

    public ?Connection $deleting = null;

    #[Title('Connections')]
    public function mount(System $system): void
    {
        $this->system = $system;
    }

    #[Computed]
    public function connections(): Collection
    {
        return $this->system->connections()->with('authProfile')->orderBy('name')->get();
    }

    #[Computed]
    public function authProfileOptions(): Collection
    {
        return $this->system->authProfiles()->orderBy('name')->get();
    }

    /**
     * Open the modal to register a new connection.
     */
    public function create(): void
    {
        $this->reset(['editingId', 'name', 'auth_profile_id']);
        $this->resetValidation();

        Flux::modal('connection-form')->show();
    }

    /**
     * Open the modal pre-filled to edit an existing connection.
     */
    public function edit(int $connectionId): void
    {
        $connection = $this->system->connections()->findOrFail($connectionId);

        $this->editingId = $connection->id;
        $this->name = $connection->name;
        $this->auth_profile_id = $connection->auth_profile_id;
        $this->resetValidation();

        Flux::modal('connection-form')->show();
    }

    /**
     * Persist the create/edit form.
     */
    public function save(): void
    {
        $validated = $this->validate();

        if ($this->editingId) {
            $this->system->connections()->findOrFail($this->editingId)->update($validated);
            Flux::toast(variant: 'success', text: __('Connection updated.'));
        } else {
            $this->system->connections()->create($validated);
            Flux::toast(variant: 'success', text: __('Connection created.'));
        }

        unset($this->connections);
        Flux::modal('connection-form')->close();
    }

    /**
     * Attempt to reach the system using this connection's credentials.
     */
    public function test(int $connectionId): void
    {
        $connection = $this->system->connections()->findOrFail($connectionId);

        $ok = $connection->test();

        unset($this->connections);
        Flux::toast(
            variant: $ok ? 'success' : 'danger',
            text: $ok ? __('Connection successful.') : __('Connection failed: :error', ['error' => $connection->last_error]),
        );
    }

    /**
     * Ask for confirmation before deleting a connection.
     */
    public function confirmDelete(int $connectionId): void
    {
        $this->deleting = $this->system->connections()->findOrFail($connectionId);

        Flux::modal('confirm-connection-deletion')->show();
    }

    /**
     * Delete the connection.
     */
    public function delete(): void
    {
        $this->deleting?->delete();
        $this->deleting = null;

        unset($this->connections);
        Flux::modal('confirm-connection-deletion')->close();
        Flux::toast(variant: 'success', text: __('Connection deleted.'));
    }
}
