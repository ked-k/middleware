<?php

namespace App\Livewire\Integrations;

use App\Models\Connection;
use App\Models\Endpoint;
use App\Models\Integration;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Integrations')]
class Index extends Component
{
    public string $search = '';

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:2000')]
    public string $description = '';

    #[Validate('required|exists:connections,id')]
    public ?int $source_connection_id = null;

    #[Validate('required|exists:endpoints,id')]
    public ?int $source_endpoint_id = null;

    #[Validate('required|exists:connections,id')]
    public ?int $target_connection_id = null;

    #[Validate('required|exists:endpoints,id')]
    public ?int $target_endpoint_id = null;

    public ?Integration $deleting = null;

    #[Computed]
    public function integrations(): Collection
    {
        return Integration::query()
            ->with(['sourceConnection.system', 'targetConnection.system'])
            ->withCount('fieldMappings')
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function connections(): Collection
    {
        return Connection::query()->with('system')->orderBy('name')->get();
    }

    #[Computed]
    public function sourceEndpoints(): Collection
    {
        return $this->endpointsForConnection($this->source_connection_id);
    }

    #[Computed]
    public function targetEndpoints(): Collection
    {
        return $this->endpointsForConnection($this->target_connection_id);
    }

    protected function endpointsForConnection(?int $connectionId): Collection
    {
        if (! $connectionId) {
            return collect();
        }

        $connection = Connection::find($connectionId);

        if (! $connection) {
            return collect();
        }

        return Endpoint::query()
            ->where('system_id', $connection->system_id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Clear the chosen source endpoint whenever the source connection changes,
     * since it belongs to a different system's endpoint list now.
     */
    public function updatedSourceConnectionId(): void
    {
        $this->source_endpoint_id = null;
    }

    /**
     * Clear the chosen target endpoint whenever the target connection changes.
     */
    public function updatedTargetConnectionId(): void
    {
        $this->target_endpoint_id = null;
    }

    /**
     * Open the modal to create a new integration.
     */
    public function create(): void
    {
        $this->reset([
            'name', 'description', 'source_connection_id', 'source_endpoint_id',
            'target_connection_id', 'target_endpoint_id',
        ]);
        $this->resetValidation();

        Flux::modal('integration-form')->show();
    }

    /**
     * Persist the new integration and jump straight to its field mapper.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $integration = Integration::create($validated);

        Flux::modal('integration-form')->close();

        $this->redirectRoute('integrations.show', $integration, navigate: true);
    }

    /**
     * Ask for confirmation before deleting an integration.
     */
    public function confirmDelete(int $integrationId): void
    {
        $this->deleting = Integration::findOrFail($integrationId);

        Flux::modal('confirm-integration-deletion')->show();
    }

    /**
     * Delete the integration and its field mappings.
     */
    public function delete(): void
    {
        $this->deleting?->delete();
        $this->deleting = null;

        unset($this->integrations);
        Flux::modal('confirm-integration-deletion')->close();
        Flux::toast(variant: 'success', text: __('Integration deleted.'));
    }
}
