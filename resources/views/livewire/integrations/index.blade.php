<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Integrations') }}</flux:heading>
            <flux:subheading>{{ __('Source-to-target pairings between registered endpoints') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('New integration') }}
        </flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search integrations...')" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Source') }}</flux:table.column>
            <flux:table.column>{{ __('Target') }}</flux:table.column>
            <flux:table.column>{{ __('Mappings') }}</flux:table.column>
            <flux:table.column>{{ __('Schedule') }}</flux:table.column>
            <flux:table.column>{{ __('Last run') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->integrations as $integration)
                <flux:table.row wire:key="integration-{{ $integration->id }}">
                    <flux:table.cell>
                        <flux:link href="{{ route('integrations.show', $integration) }}" wire:navigate class="font-medium">
                            {{ $integration->name }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $integration->sourceConnection->system->name }} &middot; {{ $integration->sourceConnection->name }}</flux:table.cell>
                    <flux:table.cell>{{ $integration->targetConnection->system->name }} &middot; {{ $integration->targetConnection->name }}</flux:table.cell>
                    <flux:table.cell>{{ $integration->field_mappings_count }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($integration->schedule_cron)
                            <code class="text-xs">{{ $integration->schedule_cron }}</code>
                        @else
                            <flux:text size="sm">{{ __('Manual') }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($integration->last_run_status)
                            <flux:badge
                                size="sm"
                                :color="match ($integration->last_run_status) {
                                    'success' => 'lime',
                                    'partial' => 'amber',
                                    'failed' => 'red',
                                    default => 'zinc',
                                }"
                            >
                                {{ ucfirst($integration->last_run_status) }}
                            </flux:badge>
                            <flux:text size="sm" class="block">{{ $integration->last_run_at?->diffForHumans() }}</flux:text>
                        @else
                            <flux:text size="sm">{{ __('Never') }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$integration->is_active ? 'lime' : 'zinc'" size="sm">
                            {{ $integration->is_active ? __('Active') : __('Inactive') }}
                        </flux:badge>
                        @if ($integration->consecutive_failures >= 3)
                            <flux:badge color="red" size="sm">
                                {{ __(':count failures in a row', ['count' => $integration->consecutive_failures]) }}
                            </flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="filled" :href="route('integrations.show', $integration)" wire:navigate>
                                {{ __('Open') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $integration->id }})">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">
                        <flux:text class="py-6 text-center">{{ __('No integrations yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="integration-form" class="max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ __('New integration') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required autofocus />
            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:subheading>{{ __('Source') }}</flux:subheading>

                    <flux:select wire:model.live="source_connection_id" :label="__('Connection')">
                        <flux:select.option value="">{{ __('Select a connection') }}</flux:select.option>
                        @foreach ($this->connections as $connection)
                            <flux:select.option value="{{ $connection->id }}">{{ $connection->system->name }} &middot; {{ $connection->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="source_endpoint_id" :label="__('Endpoint')" :disabled="! $source_connection_id">
                        <flux:select.option value="">{{ __('Select an endpoint') }}</flux:select.option>
                        @foreach ($this->sourceEndpoints as $endpoint)
                            <flux:select.option value="{{ $endpoint->id }}">{{ $endpoint->method }} {{ $endpoint->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:subheading>{{ __('Target') }}</flux:subheading>

                    <flux:select wire:model.live="target_connection_id" :label="__('Connection')">
                        <flux:select.option value="">{{ __('Select a connection') }}</flux:select.option>
                        @foreach ($this->connections as $connection)
                            <flux:select.option value="{{ $connection->id }}">{{ $connection->system->name }} &middot; {{ $connection->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="target_endpoint_id" :label="__('Endpoint')" :disabled="! $target_connection_id">
                        <flux:select.option value="">{{ __('Select an endpoint') }}</flux:select.option>
                        @foreach ($this->targetEndpoints as $endpoint)
                            <flux:select.option value="{{ $endpoint->id }}">{{ $endpoint->method }} {{ $endpoint->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create & map fields') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-integration-deletion" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this integration?') }}</flux:heading>
                <flux:subheading>{{ __('Its field mappings will be deleted too. This cannot be undone.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
