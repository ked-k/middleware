<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Lookup tables') }}</flux:heading>
            <flux:subheading>{{ __('Translate one system\'s codes and labels into another\'s — e.g. NIMS "Blood" → SyncLab sample type 1. Used by the "Lookup table" transform step.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('New lookup table') }}
        </flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search lookup tables...')" class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Entries') }}</flux:table.column>
            <flux:table.column>{{ __('When no match') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->valueMaps as $map)
                <flux:table.row wire:key="value-map-{{ $map->id }}">
                    <flux:table.cell>
                        <div class="font-medium">{{ $map->name }}</div>
                        @if ($map->description)
                            <flux:text size="sm" class="line-clamp-1">{{ $map->description }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ count($map->entries ?? []) }}
                        @if ($unmapped = $map->unmappedCount())
                            <flux:badge color="amber" size="sm" class="ms-1">{{ __(':count without a target value', ['count' => $unmapped]) }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:text size="sm">
                            {{ match ($map->fallback) {
                                'pass' => __('Pass the value through unchanged'),
                                'null' => __('Leave empty'),
                                'default' => __('Use ":value"', ['value' => $map->fallback_value]),
                                default => __('Fail the record'),
                            } }}
                        </flux:text>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="filled" wire:click="edit({{ $map->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $map->id }})">{{ __('Delete') }}</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <flux:text class="py-6 text-center">{{ __('No lookup tables yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="value-map-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit lookup table') : __('New lookup table') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" placeholder="NIMS specimen type → SyncLab sample_type_id" required />

            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

            <flux:textarea
                wire:model="entries_text"
                :label="__('Entries — one per line: source value => target value')"
                :description="__('A line without \'=>\' (or with nothing after it) is a known source value whose target isn\'t decided yet — records with it fail with a clear message until you fill it in. Lines starting with # are ignored.')"
                rows="10"
                class="font-mono"
                placeholder="Blood => 1&#10;Swab => 2&#10;Urine =>"
            />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="fallback" :label="__('When a value has no entry')">
                    <flux:select.option value="fail">{{ __('Fail the record') }}</flux:select.option>
                    <flux:select.option value="pass">{{ __('Pass the value through unchanged') }}</flux:select.option>
                    <flux:select.option value="null">{{ __('Leave empty') }}</flux:select.option>
                    <flux:select.option value="default">{{ __('Use a default value') }}</flux:select.option>
                </flux:select>

                @if ($fallback === 'default')
                    <flux:input wire:model="fallback_value" :label="__('Default value')" />
                @endif
            </div>

            <flux:switch wire:model="case_insensitive" :label="__('Ignore case and surrounding spaces when matching')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-value-map-deletion" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this lookup table?') }}</flux:heading>
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
