<section class="w-full space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:link href="{{ route('integrations.index') }}" wire:navigate class="text-sm">
                &larr; {{ __('Integrations') }}
            </flux:link>
            <flux:heading size="xl" class="mt-1">{{ $integration->name }}</flux:heading>
            @if ($integration->description)
                <flux:subheading>{{ $integration->description }}</flux:subheading>
            @endif
        </div>

        <div class="flex items-center gap-3">
            @if ($integration->last_run_status)
                <div class="text-right">
                    <flux:badge
                        size="sm"
                        :color="match ($integration->last_run_status) {
                            'success' => 'lime',
                            'partial' => 'amber',
                            'failed' => 'red',
                            default => 'zinc',
                        }"
                    >
                        {{ __('Last run: :status', ['status' => ucfirst($integration->last_run_status)]) }}
                    </flux:badge>
                    <flux:text size="sm" class="block">{{ $integration->last_run_at?->diffForHumans() }}</flux:text>
                    @if ($integration->consecutive_failures >= 3)
                        <flux:badge color="red" size="sm" class="mt-1">
                            {{ __(':count in a row', ['count' => $integration->consecutive_failures]) }}
                        </flux:badge>
                    @endif
                </div>
            @endif

            <flux:button variant="filled" icon="eye" wire:click="openPreview">
                {{ __('Preview') }}
            </flux:button>

            <flux:button variant="primary" icon="play" wire:click="run" wire:loading.attr="disabled" wire:target="run">
                {{ __('Run now') }}
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm" class="uppercase tracking-wide">{{ __('Source') }}</flux:text>
            <flux:heading class="mt-1">{{ $integration->sourceConnection->system->name }}</flux:heading>
            <flux:text size="sm">{{ $integration->sourceConnection->name }} &middot; {{ $integration->sourceEndpoint->method }} {{ $integration->sourceEndpoint->name }}</flux:text>
            <code class="mt-1 block text-xs text-zinc-500">{{ $integration->sourceEndpoint->path }}</code>
        </div>

        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm" class="uppercase tracking-wide">{{ __('Target') }}</flux:text>
            <flux:heading class="mt-1">{{ $integration->targetConnection->system->name }}</flux:heading>
            <flux:text size="sm">{{ $integration->targetConnection->name }} &middot; {{ $integration->targetEndpoint->method }} {{ $integration->targetEndpoint->name }}</flux:text>
            <code class="mt-1 block text-xs text-zinc-500">{{ $integration->targetEndpoint->path }}</code>
        </div>
    </div>

    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:subheading>{{ __('Schedule') }}</flux:subheading>

        <form wire:submit="saveSchedule" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:input
                wire:model="schedule_cron"
                :label="__('Cron expression')"
                placeholder="*/15 * * * *"
                class="max-w-xs"
            />

            <flux:button type="submit" variant="filled">{{ __('Save schedule') }}</flux:button>

            <flux:text size="sm" class="sm:ml-2">
                @if ($integration->schedule_cron && $this->nextRunAt)
                    {{ __('Next run :time', ['time' => $this->nextRunAt->diffForHumans()]) }}
                @elseif ($integration->schedule_cron)
                    {{ __('Invalid cron expression — this integration will not run on a schedule.') }}
                @else
                    {{ __('Manual only. Leave blank and this integration only runs when you click Run now.') }}
                @endif
            </flux:text>
        </form>
    </div>

    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:subheading>{{ __('Records & delivery') }}</flux:subheading>
        <flux:text size="sm" class="mt-1">
            {{ __('Where the list of records is in the source response, how each record is identified (so it is only delivered once), and how records are sent to the target.') }}
        </flux:text>

        @if ($hint = $this->wildcardHint)
            <flux:callout variant="warning" icon="exclamation-triangle" class="mt-4">
                <flux:callout.heading>{{ __('Your mappings use list paths, but list mode isn\'t set up to match') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Mappings like ":example" work on the whole response, so nothing is read. Set:', ['example' => ($hint['source'] ?? 'data').'.*.identifier']) }}
                    @if ($hint['source']) {{ __('list path ":p"', ['p' => $hint['source']]) }}@endif
                    @if ($hint['source'] && $hint['target']) · @endif
                    @if ($hint['target']) {{ __('send in batches wrapped in ":p"', ['p' => $hint['target']]) }}@endif
                    — {{ __('the existing mappings then work as-is.') }}
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button size="sm" wire:click="applyWildcardHint">{{ __('Fill these in') }}</flux:button>
                </x-slot>
            </flux:callout>
        @endif

        <form wire:submit="saveDeliverySettings" class="mt-4 space-y-5">
            <flux:switch wire:model.live="is_bulk" :label="__('The source returns a list of records')" />

            @if ($is_bulk)
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="source_collection_path"
                        :label="__('List path in the source response')"
                        placeholder="data"
                        :description="__('Leave blank if the response itself is the list.')"
                    />

                    <flux:select wire:model.live="bulk_mode" :label="__('How to send records')">
                        <flux:select.option value="per_item">{{ __('One request per record (single-record endpoint)') }}</flux:select.option>
                        <flux:select.option value="single_request">{{ __('In batches (bulk endpoint)') }}</flux:select.option>
                    </flux:select>
                </div>

                @if ($bulk_mode === 'single_request')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <flux:input wire:model="batch_size" type="number" min="1" :label="__('Max records per request')" placeholder="100" :description="__('Blank = everything in one request.')" />
                        <flux:input wire:model="target_wrapper_path" :label="__('Wrap the batch in')" placeholder="samples" :description="__('samples → {&quot;samples&quot;: [...]}. Blank = a bare JSON array.')" />
                        <flux:input wire:model="response_collection_path" :label="__('Per-record results in the response')" placeholder="data.samples" />
                    </div>
                @endif
            @endif

            <flux:separator variant="subtle" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:input wire:model="source_key_field" :label="__('Source record ID')" placeholder="identifier" list="source-field-suggestions" :description="__('Must be unique per record. Enables de-duplication and the synced-records ledger.')" />
                <flux:input wire:model="target_key_field" :label="__('Same ID in the target payload')" placeholder="sid" list="target-field-suggestions" :description="__('Used to match batch results back to records.')" />
                <flux:input wire:model="response_id_path" :label="__('ID the target assigns')" :placeholder="$is_bulk && $bulk_mode === 'single_request' ? 'lab_no' : 'data.lab_no'" :description="$is_bulk && $bulk_mode === 'single_request' ? __('Relative to each per-record result.') : __('Path in the response body.')" />
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:gap-8">
                <flux:switch wire:model="skip_synced" :label="__('Skip records already delivered')" />
                <flux:switch wire:model="resend_on_change" :label="__('…unless their mapped data changed')" />
            </div>

            <flux:button type="submit" variant="filled">{{ __('Save delivery settings') }}</flux:button>
        </form>
    </div>

    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:subheading>{{ __('Record filters') }}</flux:subheading>
                <flux:text size="sm" class="mt-1">{{ __('Only records matching every condition are sent. Paths are relative to one record; use $root.… for top-level values.') }}</flux:text>
            </div>
            <flux:button size="sm" variant="filled" icon="plus" wire:click="addFilter">{{ __('Add condition') }}</flux:button>
        </div>

        <form wire:submit="saveFilters" class="mt-4 space-y-3">
            @forelse ($filters as $i => $filter)
                <div class="grid grid-cols-1 items-start gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]" wire:key="filter-{{ $i }}">
                    <flux:input wire:model="filters.{{ $i }}.field" placeholder="status" list="source-field-suggestions" />
                    <flux:select wire:model.live="filters.{{ $i }}.operator">
                        @foreach (\App\Support\Mapping\RecordFilter::OPERATORS as $op => $label)
                            <flux:select.option value="{{ $op }}">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if (! in_array($filter['operator'], ['empty', 'not_empty'], true))
                        <flux:input wire:model="filters.{{ $i }}.value" placeholder="Result Added, Received" />
                    @else
                        <div></div>
                    @endif
                    <flux:button variant="ghost" icon="x-mark" wire:click="removeFilter({{ $i }})" />
                </div>
            @empty
                <flux:text size="sm">{{ __('No filters — every record is sent.') }}</flux:text>
            @endforelse

            <flux:button type="submit" variant="filled">{{ __('Save filters') }}</flux:button>
        </form>
    </div>

    <div class="flex items-center justify-between gap-4">
        <flux:subheading>{{ __('Field mappings build one target record from one source record, top to bottom. Each mapping reads a source field, runs its transform steps in order, and writes the result to the target field.') }}</flux:subheading>

        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('Add mapping') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('#') }}</flux:table.column>
            <flux:table.column>{{ __('Source field') }}</flux:table.column>
            <flux:table.column></flux:table.column>
            <flux:table.column>{{ __('Target field') }}</flux:table.column>
            <flux:table.column>{{ __('Transform steps') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->mappings as $index => $mapping)
                <flux:table.row wire:key="mapping-{{ $mapping->id }}">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <button type="button" wire:click="moveUp({{ $mapping->id }})" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" @if($index === 0) disabled @endif>
                                <flux:icon.chevron-up variant="micro" />
                            </button>
                            <button type="button" wire:click="moveDown({{ $mapping->id }})" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" @if($index === $this->mappings->count() - 1) disabled @endif>
                                <flux:icon.chevron-down variant="micro" />
                            </button>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($mapping->source_field)
                            <code class="text-sm">{{ $mapping->source_field }}</code>
                        @else
                            <flux:text size="sm" class="italic">{{ __('(generated)') }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell><flux:icon.arrow-right variant="micro" class="text-zinc-400" /></flux:table.cell>
                    <flux:table.cell>
                        <code class="text-sm">{{ $mapping->target_field }}</code>
                        <div class="mt-1 flex gap-1">
                            @if ($mapping->is_required)
                                <flux:badge size="sm" color="red">{{ __('Required') }}</flux:badge>
                            @endif
                            @if ($mapping->skip_if_empty)
                                <flux:badge size="sm" color="zinc">{{ __('Omit if empty') }}</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-md">
                        @php($described = $mapping->describeSteps($this->valueMaps))
                        @if ($described === [])
                            <flux:text size="sm">{{ __('Copy as-is') }}</flux:text>
                        @else
                            <div class="flex flex-wrap items-center gap-1">
                                @foreach ($described as $n => $label)
                                    @if ($n > 0)
                                        <flux:icon.chevron-right variant="micro" class="text-zinc-400" />
                                    @endif
                                    <flux:badge size="sm" class="max-w-xs truncate" title="{{ $label }}">{{ $label }}</flux:badge>
                                @endforeach
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="filled" wire:click="edit({{ $mapping->id }})">
                                {{ __('Edit') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $mapping->id }})">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text class="py-6 text-center">{{ __('No field mappings yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <datalist id="source-field-suggestions">
        @foreach ($this->sourceFieldSuggestions as $path)
            <option value="{{ $path }}"></option>
        @endforeach
    </datalist>
    <datalist id="target-field-suggestions">
        @foreach ($this->targetFieldSuggestions as $path)
            <option value="{{ $path }}"></option>
        @endforeach
    </datalist>

    <flux:modal name="field-mapping-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit field mapping') : __('Add field mapping') }}
            </flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input
                    wire:model="source_field"
                    :label="__('Source field')"
                    placeholder="specimen_type"
                    list="source-field-suggestions"
                    :description="__('Blank when a Constant or Template step produces the value. $root.… reads outside the record.')"
                    autofocus
                />
                <flux:input wire:model="target_field" :label="__('Target field')" placeholder="sample_type_id" list="target-field-suggestions" required />
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:label>{{ __('Transform steps (run top to bottom)') }}</flux:label>
                    <flux:button size="sm" variant="filled" icon="plus" wire:click="addStep">{{ __('Add step') }}</flux:button>
                </div>

                @forelse ($steps as $i => $step)
                    @php($meta = \App\Support\Mapping\Transforms::TYPES[$step['type']] ?? null)
                    <div class="rounded-md border border-zinc-200 p-3 dark:border-zinc-700" wire:key="step-{{ $i }}">
                        <div class="grid grid-cols-1 items-start gap-2 sm:grid-cols-[auto_1fr_1.4fr_auto]">
                            <div class="flex flex-col pt-1">
                                <button type="button" wire:click="moveStep({{ $i }}, -1)" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"><flux:icon.chevron-up variant="micro" /></button>
                                <button type="button" wire:click="moveStep({{ $i }}, 1)" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"><flux:icon.chevron-down variant="micro" /></button>
                            </div>

                            <flux:select wire:model.live="steps.{{ $i }}.type">
                                @foreach (\App\Support\Mapping\Transforms::TYPES as $type => $typeMeta)
                                    <flux:select.option value="{{ $type }}">{{ __($typeMeta[0]) }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            @if ($meta && $meta[1] !== null)
                                @if ($step['type'] === 'lookup')
                                    <flux:select wire:model="steps.{{ $i }}.param" :placeholder="__('Choose a lookup table…')">
                                        @foreach ($this->valueMaps as $id => $name)
                                            <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @elseif ($step['type'] === 'template')
                                    <flux:textarea wire:model="steps.{{ $i }}.param" rows="2" :placeholder="$meta[2]" class="font-mono" />
                                @else
                                    <flux:input wire:model="steps.{{ $i }}.param" :placeholder="$meta[2]" />
                                @endif
                            @else
                                <div></div>
                            @endif

                            <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="removeStep({{ $i }})" />
                        </div>
                        <flux:error name="steps.{{ $i }}.param" />
                        @if ($step['type'] === 'template')
                            <flux:text size="sm" class="mt-2">{{ __('{field} inserts a record value, {value} the incoming value, {$root.field} a top-level value. Wrap a section in [ ] to drop it when any of its fields are empty.') }}</flux:text>
                        @elseif ($step['type'] === 'lookup' && $this->valueMaps === [])
                            <flux:text size="sm" class="mt-2">
                                {{ __('No lookup tables yet —') }} <flux:link href="{{ route('value-maps.index') }}" wire:navigate>{{ __('create one') }}</flux:link>.
                            </flux:text>
                        @endif
                    </div>
                @empty
                    <flux:text size="sm">{{ __('No steps — the source value is copied as-is.') }}</flux:text>
                @endforelse
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:gap-8">
                <flux:checkbox wire:model="is_required" :label="__('Required — fail the record if empty')" />
                <flux:checkbox wire:model="skip_if_empty" :label="__('Omit from payload if empty')" />
            </div>

            <flux:error name="source_field" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="preview" class="w-full max-w-5xl">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Preview mapping') }}</flux:heading>
                <flux:subheading>{{ __('Maps records exactly like a real run, but sends nothing and records nothing.') }}</flux:subheading>
            </div>

            <flux:textarea
                wire:model="preview_json"
                :label="__('Sample source response (optional)')"
                :description="__('Paste a response from the source to test against it. Leave blank to call the live source endpoint.')"
                rows="6"
                class="font-mono text-xs"
            />

            <flux:button variant="primary" icon="eye" wire:click="runPreview" wire:loading.attr="disabled" wire:target="runPreview">
                {{ __('Run preview') }}
            </flux:button>

            @if ($preview_error)
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="$preview_error" />
            @endif

            @if ($preview)
                <div class="flex flex-wrap gap-2">
                    <flux:badge>{{ __(':n records', ['n' => $preview['stats']['fetched']]) }}</flux:badge>
                    <flux:badge color="lime">{{ __(':n ready to send', ['n' => $preview['stats']['ready']]) }}</flux:badge>
                    <flux:badge color="red">{{ __(':n invalid', ['n' => $preview['stats']['invalid']]) }}</flux:badge>
                    <flux:badge color="zinc">{{ __(':n filtered out', ['n' => $preview['stats']['filtered']]) }}</flux:badge>
                    <flux:badge color="zinc">{{ __(':n duplicates', ['n' => $preview['stats']['duplicates']]) }}</flux:badge>
                    <flux:badge color="sky">{{ __(':n already synced', ['n' => $preview['stats']['already_synced']]) }}</flux:badge>
                    <flux:badge color="violet">{{ __(':n request(s)', ['n' => $preview['stats']['requests']]) }}</flux:badge>
                </div>

                <div class="max-h-[28rem] overflow-y-auto">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Record') }}</flux:table.column>
                            <flux:table.column>{{ __('Outcome') }}</flux:table.column>
                            <flux:table.column>{{ __('Mapped payload / problems') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($preview['items'] as $item)
                                <flux:table.row wire:key="preview-{{ $item['index'] }}">
                                    <flux:table.cell class="align-top"><code class="text-xs">{{ $item['key'] ?? '#'.$item['index'] }}</code></flux:table.cell>
                                    <flux:table.cell class="align-top">
                                        <flux:badge size="sm" :color="match ($item['status']) { 'ready' => 'lime', 'invalid' => 'red', 'already_synced' => 'sky', default => 'zinc' }">
                                            {{ str($item['status'])->headline() }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="align-top">
                                        @foreach ($item['errors'] as $error)
                                            <flux:text size="sm" class="text-red-600 dark:text-red-400">{{ $error }}</flux:text>
                                        @endforeach
                                        @if ($item['reason'])
                                            <flux:text size="sm">{{ $item['reason'] }}</flux:text>
                                        @endif
                                        @if ($item['payload'])
                                            <pre class="mt-1 whitespace-pre-wrap break-all text-xs text-zinc-600 dark:text-zinc-300">{{ json_encode($item['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>

                @if ($preview['request_body'] !== null)
                    <div>
                        <flux:label>{{ __('First request body that would be sent') }}</flux:label>
                        <pre class="mt-1 max-h-72 overflow-auto rounded-md bg-zinc-100 p-3 text-xs dark:bg-zinc-900">{{ json_encode($preview['request_body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>

    <flux:modal name="confirm-mapping-deletion" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Remove this field mapping?') }}</flux:heading>
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

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="lg">{{ __('Synced records') }}</flux:heading>
            <flux:subheading>
                @if ($integration->source_key_field)
                    {{ __('Every record seen, keyed by :field. Synced records are skipped on later runs; failed and invalid ones are tried again.', ['field' => $integration->source_key_field]) }}
                @else
                    {{ __('Set a "Source record ID" under Records & delivery to track records across runs.') }}
                @endif
            </flux:subheading>
            <div class="mt-2 flex flex-wrap gap-2">
                <flux:badge size="sm" color="lime">{{ __(':n synced', ['n' => $this->recordCounts['synced'] ?? 0]) }}</flux:badge>
                <flux:badge size="sm" color="red">{{ __(':n failed', ['n' => $this->recordCounts['failed'] ?? 0]) }}</flux:badge>
                <flux:badge size="sm" color="amber">{{ __(':n invalid', ['n' => $this->recordCounts['invalid'] ?? 0]) }}</flux:badge>
            </div>
        </div>

        <div class="flex gap-2">
            <flux:input wire:model.live.debounce.300ms="recordSearch" icon="magnifying-glass" :placeholder="__('Source or target ID…')" class="max-w-xs" />
            <flux:select wire:model.live="recordStatus" class="max-w-40">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach (\App\Models\IntegrationRecord::STATUSES as $status)
                    <flux:select.option value="{{ $status }}">{{ ucfirst($status) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Source ID') }}</flux:table.column>
            <flux:table.column>{{ __('Target ID') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Attempts') }}</flux:table.column>
            <flux:table.column>{{ __('Last update') }}</flux:table.column>
            <flux:table.column>{{ __('Error') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->records as $record)
                <flux:table.row wire:key="record-{{ $record->id }}">
                    <flux:table.cell><code class="text-sm">{{ $record->source_key }}</code></flux:table.cell>
                    <flux:table.cell>{{ $record->target_key ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($record->status) { 'synced' => 'lime', 'failed' => 'red', default => 'amber' }">
                            {{ ucfirst($record->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $record->attempts }}</flux:table.cell>
                    <flux:table.cell>{{ $record->updated_at?->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell class="max-w-sm">
                        @if ($record->last_error)
                            <flux:text size="sm" class="line-clamp-2" title="{{ $record->last_error }}">{{ $record->last_error }}</flux:text>
                        @else
                            &mdash;
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($record->status === 'synced')
                            <flux:button size="sm" variant="ghost" wire:click="forgetRecord({{ $record->id }})" wire:confirm="{{ __('Forget this record? It will be sent to the target again on the next run.') }}">
                                {{ __('Resend') }}
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">
                        <flux:text class="py-6 text-center">{{ __('No records tracked yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->records->links() }}

    <div>
        <flux:heading size="lg">{{ __('Run history') }}</flux:heading>
        <flux:subheading>{{ __('Every execution attempt, newest first') }}</flux:subheading>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Started') }}</flux:table.column>
            <flux:table.column>{{ __('Trigger') }}</flux:table.column>
            <flux:table.column>{{ __('Attempt') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Duration') }}</flux:table.column>
            <flux:table.column>{{ __('Error') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->runs as $run)
                <flux:table.row wire:key="run-{{ $run->id }}">
                    <flux:table.cell>{{ $run->started_at?->diffForHumans() ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ ucfirst($run->trigger) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $run->attempt }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge
                            size="sm"
                            :color="match ($run->status) {
                                'success' => 'lime',
                                'partial' => 'amber',
                                'failed' => 'red',
                                default => 'zinc',
                            }"
                        >
                            {{ ucfirst($run->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $run->duration_ms !== null ? "{$run->duration_ms} ms" : '—' }}</flux:table.cell>
                    <flux:table.cell class="max-w-xs">
                        @if ($run->error)
                            <flux:text size="sm" class="line-clamp-2" title="{{ $run->error }}">{{ $run->error }}</flux:text>
                        @else
                            &mdash;
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                        <flux:button size="sm" variant="ghost" wire:click="showRun({{ $run->id }})">{{ __('Details') }}</flux:button>
                        @if (in_array($run->status, ['failed', 'partial'], true))
                            <flux:button size="sm" variant="filled" wire:click="retry({{ $run->id }})" wire:loading.attr="disabled" wire:target="retry({{ $run->id }})">
                                {{ __('Retry') }}
                            </flux:button>
                        @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">
                        <flux:text class="py-6 text-center">{{ __('This integration has not run yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->runs->links() }}

    <flux:modal name="run-details" class="w-full max-w-3xl">
        @if ($run = $this->viewingRun)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Run #:id', ['id' => $run->id]) }}</flux:heading>
                    <flux:subheading>{{ ucfirst($run->trigger) }} &middot; {{ ucfirst($run->status) }} &middot; {{ $run->started_at?->toDayDateTimeString() }}</flux:subheading>
                </div>

                @if ($run->error)
                    <flux:callout variant="danger" icon="exclamation-triangle">
                        <flux:callout.text class="whitespace-pre-wrap break-all">{{ $run->error }}</flux:callout.text>
                    </flux:callout>
                @endif

                <div>
                    <flux:label>{{ __('Outcome') }}</flux:label>
                    <pre class="mt-1 max-h-64 overflow-auto rounded-md bg-zinc-100 p-3 text-xs dark:bg-zinc-900">{{ json_encode($run->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>

                <div>
                    <flux:label>{{ __('Request sample') }}</flux:label>
                    <pre class="mt-1 max-h-64 overflow-auto rounded-md bg-zinc-100 p-3 text-xs dark:bg-zinc-900">{{ json_encode($run->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
