<section class="w-full space-y-6">
    <div>
        <flux:link href="{{ route('systems.index') }}" wire:navigate class="text-sm">
            &larr; {{ __('Systems') }}
        </flux:link>
        <flux:heading size="xl" class="mt-1">{{ $system->name }}</flux:heading>
    </div>

    <x-systems.tabs :system="$system" active="auth-profiles" />

    <div class="flex items-center justify-between gap-4">
        <flux:subheading>{{ __('Credential sets used to authenticate against this system') }}</flux:subheading>

        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('Add auth profile') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->authProfiles as $profile)
                <flux:table.row wire:key="auth-profile-{{ $profile->id }}">
                    <flux:table.cell class="font-medium">{{ $profile->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $profile->type }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="filled" wire:click="edit({{ $profile->id }})">
                                {{ __('Edit') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $profile->id }})">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">
                        <flux:text class="py-6 text-center">{{ __('No auth profiles yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="auth-profile-form" class="max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit auth profile') : __('Add auth profile') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required autofocus />

            <flux:select wire:model.live="type" :label="__('Type')">
                @foreach (\App\Models\AuthProfile::TYPES as $authType)
                    <flux:select.option value="{{ $authType }}">{{ $authType }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($type === 'api_key')
                <flux:input wire:model="header_name" :label="__('Header name')" />
                <flux:input wire:model="api_key" :label="__('API key')" type="password" viewable :placeholder="$editingId ? __('Leave blank to keep the current value') : ''" />
            @elseif ($type === 'basic')
                <flux:input wire:model="username" :label="__('Username')" />
                <flux:input wire:model="password" :label="__('Password')" type="password" viewable :placeholder="$editingId ? __('Leave blank to keep the current value') : ''" />
            @elseif ($type === 'bearer')
                <flux:input wire:model="token" :label="__('Bearer token')" type="password" viewable :placeholder="$editingId ? __('Leave blank to keep the current value') : ''" />
            @elseif ($type === 'oauth2')
                <flux:input wire:model="client_id" :label="__('Client ID')" />
                <flux:input wire:model="client_secret" :label="__('Client secret')" type="password" viewable :placeholder="$editingId ? __('Leave blank to keep the current value') : ''" />
                <flux:input wire:model="token_url" :label="__('Token URL')" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-auth-profile-deletion" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this auth profile?') }}</flux:heading>
                <flux:subheading>
                    {{ __('Connections using it will need a new auth profile assigned. This cannot be undone.') }}
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
