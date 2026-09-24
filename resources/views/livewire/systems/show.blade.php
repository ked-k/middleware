<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:link href="{{ route('systems.index') }}" wire:navigate class="text-sm">
                &larr; {{ __('Systems') }}
            </flux:link>
            <flux:heading size="xl" class="mt-1">{{ $system->name }}</flux:heading>
            <flux:subheading>{{ $system->base_url ?: __('No base URL set') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('Register endpoint') }}
        </flux:button>
    </div>

    <x-systems.tabs :system="$system" active="endpoints" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Method') }}</flux:table.column>
            <flux:table.column>{{ __('Path') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->endpoints as $endpoint)
                <flux:table.row wire:key="endpoint-{{ $endpoint->id }}">
                    <flux:table.cell class="font-medium">{{ $endpoint->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $endpoint->method }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell><code class="text-sm">{{ $endpoint->path }}</code></flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$endpoint->is_active ? 'lime' : 'zinc'" size="sm">
                            {{ $endpoint->is_active ? __('Active') : __('Inactive') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="filled" wire:click="edit({{ $endpoint->id }})">
                                {{ __('Edit') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $endpoint->id }})">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text class="py-6 text-center">{{ __('No endpoints registered for this system yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="endpoint-form" class="max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit endpoint') : __('Register endpoint') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required autofocus />

            <div class="grid grid-cols-3 gap-4">
                <flux:select wire:model="method" :label="__('Method')" class="col-span-1">
                    @foreach (\App\Models\Endpoint::METHODS as $method)
                        <flux:select.option value="{{ $method }}">{{ $method }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="path" :label="__('Path')" placeholder="/v1/specimens" class="col-span-2" />
            </div>

            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <flux:textarea
                        wire:model="request_schema"
                        :label="__('Request schema')"
                        :description="__('JSON Schema, or a plain example payload')"
                        rows="6"
                        class="font-mono text-xs"
                        placeholder='{"name": "", "email": ""}'
                    />
                </div>

                <div>
                    <flux:textarea
                        wire:model="response_schema"
                        :label="__('Response schema')"
                        :description="__('JSON Schema, or a plain example payload')"
                        rows="6"
                        class="font-mono text-xs"
                        placeholder='{"id": 0, "name": ""}'
                    />
                </div>
            </div>

            <flux:switch wire:model="is_active" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-endpoint-deletion" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this endpoint?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This cannot be undone.') }}
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
