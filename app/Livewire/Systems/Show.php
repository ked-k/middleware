<?php

namespace App\Livewire\Systems;

use App\Models\Endpoint;
use App\Models\System;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Show extends Component
{
    public System $system;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|in:GET,POST,PUT,PATCH,DELETE')]
    public string $method = 'GET';

    #[Validate('required|string|max:255')]
    public string $path = '';

    #[Validate('nullable|string|max:2000')]
    public string $description = '';

    #[Validate('nullable|json')]
    public string $request_schema = '';

    #[Validate('nullable|json')]
    public string $response_schema = '';

    public bool $is_active = true;

    public ?Endpoint $deleting = null;

    /**
     * Set the page title once the system is known.
     */
    #[Title('System')]
    public function mount(System $system): void
    {
        $this->system = $system;
    }

    #[Computed]
    public function endpoints(): Collection
    {
        return $this->system->endpoints()->orderBy('name')->get();
    }

    /**
     * Open the modal to register a new endpoint.
     */
    public function create(): void
    {
        $this->reset(['editingId', 'name', 'path', 'description', 'request_schema', 'response_schema']);
        $this->method = 'GET';
        $this->is_active = true;
        $this->resetValidation();

        Flux::modal('endpoint-form')->show();
    }

    /**
     * Open the modal pre-filled to edit an existing endpoint.
     */
    public function edit(int $endpointId): void
    {
        $endpoint = $this->system->endpoints()->findOrFail($endpointId);

        $this->editingId = $endpoint->id;
        $this->name = $endpoint->name;
        $this->method = $endpoint->method;
        $this->path = $endpoint->path;
        $this->description = (string) $endpoint->description;
        $this->request_schema = $endpoint->request_schema ? json_encode($endpoint->request_schema, JSON_PRETTY_PRINT) : '';
        $this->response_schema = $endpoint->response_schema ? json_encode($endpoint->response_schema, JSON_PRETTY_PRINT) : '';
        $this->is_active = $endpoint->is_active;
        $this->resetValidation();

        Flux::modal('endpoint-form')->show();
    }

    /**
     * Persist the create/edit form.
     */
    public function save(): void
    {
        $validated = $this->validate();
        $validated['description'] = $validated['description'] ?: null;
        $validated['request_schema'] = $validated['request_schema'] ? json_decode($validated['request_schema'], true) : null;
        $validated['response_schema'] = $validated['response_schema'] ? json_decode($validated['response_schema'], true) : null;

        if ($this->editingId) {
            $this->system->endpoints()->findOrFail($this->editingId)->update($validated);
            Flux::toast(variant: 'success', text: __('Endpoint updated.'));
        } else {
            $this->system->endpoints()->create($validated);
            Flux::toast(variant: 'success', text: __('Endpoint registered.'));
        }

        unset($this->endpoints);
        Flux::modal('endpoint-form')->close();
    }

    /**
     * Ask for confirmation before deleting an endpoint.
     */
    public function confirmDelete(int $endpointId): void
    {
        $this->deleting = $this->system->endpoints()->findOrFail($endpointId);

        Flux::modal('confirm-endpoint-deletion')->show();
    }

    /**
     * Delete the endpoint.
     */
    public function delete(): void
    {
        $this->deleting?->delete();
        $this->deleting = null;

        unset($this->endpoints);
        Flux::modal('confirm-endpoint-deletion')->close();
        Flux::toast(variant: 'success', text: __('Endpoint deleted.'));
    }
}
