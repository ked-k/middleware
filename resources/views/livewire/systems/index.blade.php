<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Systems') }}</flux:heading>
            <flux:subheading>{{ __('External systems registered with the middleware') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('Register system') }}
        </flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search systems...')" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Base URL') }}</flux:table.column>
            <flux:table.column>{{ __('Endpoints') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->systems as $system)
                <flux:table.row wire:key="system-{{ $system->id }}">
                    <flux:table.cell>
                        <flux:link href="{{ route('systems.show', $system) }}" wire:navigate class="font-medium">
                            {{ $system->name }}
                        </flux:link>
                        <flux:text size="sm" class="block">{{ $system->slug }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>{{ $system->base_url ?: '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $system->endpoints_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$system->is_active ? 'lime' : 'zinc'" size="sm">
                            {{ $system->is_active ? __('Active') : __('Inactive') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="filled" wire:click="edit({{ $system->id }})">
                                {{ __('Edit') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $system->id }})">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text class="py-6 text-center">{{ __('No systems registered yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="system-form" class="max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit system') : __('Register system') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required autofocus />
            <flux:input wire:model="base_url" :label="__('Base URL')" placeholder="https://api.example.com" />
            <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
            <flux:switch wire:model="is_active" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-system-deletion" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this system?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This also deletes every endpoint registered under :name. This cannot be undone.', ['name' => $deleting?->name]) }}
                </flux:subheading>
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
