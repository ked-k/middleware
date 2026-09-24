<section class="w-full space-y-6">
    <div>
        <flux:link href="{{ route('systems.index') }}" wire:navigate class="text-sm">
            &larr; {{ __('Systems') }}
        </flux:link>
        <flux:heading size="xl" class="mt-1">{{ $system->name }}</flux:heading>
    </div>

    <x-systems.tabs :system="$system" active="connections" />

    <div class="flex items-center justify-between gap-4">
        <flux:subheading>{{ __('Authenticated connections established to this system') }}</flux:subheading>

        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('Add connection') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Auth profile') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Last tested') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->connections as $connection)
                <flux:table.row wire:key="connection-{{ $connection->id }}">
                    <flux:table.cell class="font-medium">{{ $connection->name }}</flux:table.cell>
                    <flux:table.cell>{{ $connection->authProfile?->name ?? __('None') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge
                            size="sm"
                            :color="match ($connection->status) {
                                'connected' => 'lime',
                                'failed' => 'red',
                                default => 'zinc',
                            }"
                        >
                            {{ ucfirst($connection->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $connection->last_tested_at?->diffForHumans() ?? __('Never') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="filled" wire:click="test({{ $connection->id }})" wire:loading.attr="disabled" wire:target="test({{ $connection->id }})">
                                {{ __('Test') }}
                            </flux:button>
                            <flux:button size="sm" variant="filled" wire:click="edit({{ $connection->id }})">
                                {{ __('Edit') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $connection->id }})">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text class="py-6 text-center">{{ __('No connections yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="connection-form" class="max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit connection') : __('Add connection') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required autofocus />

            <flux:select wire:model="auth_profile_id" :label="__('Auth profile')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($this->authProfileOptions as $profile)
                    <flux:select.option value="{{ $profile->id }}">{{ $profile->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-connection-deletion" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this connection?') }}</flux:heading>
                <flux:subheading>{{ __('This cannot be undone.') }}</flux:subheading>
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
