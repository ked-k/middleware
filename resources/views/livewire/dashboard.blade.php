@php
    $colors = \App\Livewire\Dashboard::STATUS_COLORS;
    $statusMeta = [
        'success' => ['label' => __('Success'), 'icon' => 'check-circle'],
        'partial' => ['label' => __('Partial'), 'icon' => 'exclamation-circle'],
        'failed' => ['label' => __('Failed'), 'icon' => 'x-circle'],
    ];
    $k = $this->kpis;
    $axis = $this->axis;
@endphp

<section class="w-full space-y-6">
    {{-- Header + the one filter row --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
            <flux:subheading>{{ __(':i integrations across :s systems', ['i' => $k['integrations'], 's' => $k['systems']]) }}</flux:subheading>
        </div>

        <flux:select wire:model.live="days" class="max-w-44">
            @foreach (\App\Livewire\Dashboard::RANGES as $value => $label)
                <flux:select.option value="{{ $value }}">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Stat tiles --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Runs') }}</flux:text>
            <div class="mt-1 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($k['runs']) }}</div>
            <flux:text size="sm" class="mt-1">{{ __(':n failed', ['n' => $k['failed_runs']]) }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Run success rate') }}</flux:text>
            <div class="mt-1 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                {{ $k['success_rate'] === null ? '—' : $k['success_rate'].'%' }}
            </div>
            <flux:text size="sm" class="mt-1">{{ __('success or partial') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Records delivered') }}</flux:text>
            <div class="mt-1 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($k['delivered']) }}</div>
            <flux:text size="sm" class="mt-1">{{ __(':n all time', ['n' => number_format($k['delivered_all_time'])]) }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Records needing attention') }}</flux:text>
            <div class="mt-1 flex items-center gap-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                {{ number_format($k['needs_attention']) }}
                @if ($k['needs_attention'] > 0)
                    <flux:icon.exclamation-triangle class="size-6" style="color: {{ $colors['partial'] }}" />
                @endif
            </div>
            <flux:text size="sm" class="mt-1">{{ __('failed or invalid, retried next run') }}</flux:text>
        </div>
    </div>

    {{-- Runs per day, stacked by outcome --}}
    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" x-data="{ view: 'chart' }">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading>{{ __('Runs per day') }}</flux:heading>
                <flux:text size="sm">{{ __('Every execution attempt, by outcome') }}</flux:text>
            </div>
            <div class="flex items-center gap-4">
                {{-- Legend: icon + label, never colour alone --}}
                <div class="flex flex-wrap items-center gap-3">
                    @foreach ($statusMeta as $status => $meta)
                        <span class="flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300">
                            <span class="inline-block size-2.5 rounded-sm" style="background: {{ $colors[$status] }}"></span>
                            {{ $meta['label'] }}
                        </span>
                    @endforeach
                </div>
                <flux:button size="xs" variant="ghost" x-on:click="view = view === 'chart' ? 'table' : 'chart'" x-text="view === 'chart' ? '{{ __('Table') }}' : '{{ __('Chart') }}'"></flux:button>
            </div>
        </div>

        <div x-show="view === 'chart'" class="mt-4">
            @if ($k['runs'] === 0)
                <flux:text class="py-10 text-center">{{ __('No runs in this period.') }}</flux:text>
            @else
                <div class="flex gap-2">
                    {{-- Y axis --}}
                    <div class="relative h-56 w-8 shrink-0 text-right text-xs tabular-nums text-zinc-500">
                        @foreach ($axis['ticks'] as $tick)
                            <span class="absolute right-0 -translate-y-1/2" style="bottom: {{ $tick / $axis['max'] * 100 }}%">{{ $tick }}</span>
                        @endforeach
                    </div>

                    <div class="relative flex-1">
                        {{-- Recessive gridlines --}}
                        <div class="pointer-events-none absolute inset-x-0 top-0 h-56">
                            @foreach ($axis['ticks'] as $tick)
                                <div class="absolute inset-x-0 border-t {{ $tick === 0 ? 'border-zinc-300 dark:border-zinc-600' : 'border-dashed border-zinc-200/70 dark:border-zinc-700/70' }}" style="bottom: {{ $tick / $axis['max'] * 100 }}%"></div>
                            @endforeach
                        </div>

                        {{-- Bars --}}
                        <div class="relative flex h-56 items-end gap-[2px]">
                            @foreach ($this->daily as $day)
                                <div class="group relative flex h-full flex-1 flex-col justify-end" wire:key="day-{{ $day['label'] }}">
                                    <div class="mx-auto flex w-full max-w-8 flex-col-reverse gap-[2px]" style="height: {{ $day['total'] / $axis['max'] * 100 }}%">
                                        @php($top = collect(['success', 'partial', 'failed'])->filter(fn ($s) => $day[$s] > 0)->last())
                                        @foreach (['success', 'partial', 'failed'] as $status)
                                            @if ($day[$status] > 0)
                                                <div class="w-full {{ $status === $top ? 'rounded-t' : '' }}" style="flex: {{ $day[$status] }} 1 0; min-height: 3px; background: {{ $colors[$status] }}"></div>
                                            @endif
                                        @endforeach
                                    </div>

                                    {{-- Hover target is the whole column; tooltip uses text ink, colour only on the swatch --}}
                                    @if ($day['total'] > 0)
                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden w-36 -translate-x-1/2 rounded-md border border-zinc-200 bg-white p-2 text-xs shadow-lg group-hover:block dark:border-zinc-700 dark:bg-zinc-900">
                                            <div class="mb-1 font-medium text-zinc-900 dark:text-white">{{ $day['date']->format('D j M') }}</div>
                                            @foreach ($statusMeta as $status => $meta)
                                                <div class="flex items-center justify-between gap-2 text-zinc-600 dark:text-zinc-300">
                                                    <span class="flex items-center gap-1.5"><span class="inline-block size-2 rounded-sm" style="background: {{ $colors[$status] }}"></span>{{ $meta['label'] }}</span>
                                                    <span class="tabular-nums">{{ $day[$status] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="absolute inset-0 group-hover:bg-zinc-500/5"></div>
                                </div>
                            @endforeach
                        </div>

                        {{-- X axis: label a subset so they never collide --}}
                        @php($every = $this->days > 14 ? 5 : ($this->days > 7 ? 2 : 1))
                        <div class="mt-2 flex gap-[2px] text-xs text-zinc-500">
                            @foreach ($this->daily as $i => $day)
                                <div class="flex-1 text-center">{{ ($i % $every === 0 || $loop->last) ? $day['label'] : '' }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div x-show="view === 'table'" x-cloak class="mt-4 max-h-72 overflow-y-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Day') }}</flux:table.column>
                    @foreach ($statusMeta as $meta)
                        <flux:table.column align="end">{{ $meta['label'] }}</flux:table.column>
                    @endforeach
                    <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach (array_reverse($this->daily) as $day)
                        <flux:table.row wire:key="day-row-{{ $day['label'] }}">
                            <flux:table.cell>{{ $day['date']->format('D j M') }}</flux:table.cell>
                            @foreach (array_keys($statusMeta) as $status)
                                <flux:table.cell align="end" class="tabular-nums">{{ $day[$status] }}</flux:table.cell>
                            @endforeach
                            <flux:table.cell align="end" class="tabular-nums font-medium">{{ $day['total'] }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Integration health --}}
        <div class="rounded-xl border border-zinc-200 p-4 xl:col-span-2 dark:border-zinc-700">
            <flux:heading>{{ __('Integrations') }}</flux:heading>
            <flux:text size="sm">{{ __('Failing ones first') }}</flux:text>

            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>{{ __('Integration') }}</flux:table.column>
                    <flux:table.column>{{ __('Last run') }}</flux:table.column>
                    <flux:table.column>{{ __('Next run') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Delivered') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Needs attention') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->integrations as $integration)
                        <flux:table.row wire:key="int-{{ $integration->id }}">
                            <flux:table.cell>
                                <flux:link href="{{ route('integrations.show', $integration) }}" wire:navigate class="font-medium">{{ $integration->name }}</flux:link>
                                <flux:text size="sm" class="block">
                                    {{ $integration->sourceConnection?->system?->name }} &rarr; {{ $integration->targetConnection?->system?->name }}
                                    @unless ($integration->is_active) &middot; {{ __('inactive') }} @endunless
                                </flux:text>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($integration->last_run_status && isset($statusMeta[$integration->last_run_status]))
                                    @php($meta = $statusMeta[$integration->last_run_status])
                                    <span class="flex items-center gap-1.5 text-sm text-zinc-700 dark:text-zinc-200">
                                        <flux:icon :name="$meta['icon']" variant="mini" style="color: {{ $colors[$integration->last_run_status] }}" />
                                        {{ $meta['label'] }}
                                    </span>
                                    <flux:text size="sm">{{ $integration->last_run_at?->diffForHumans() }}</flux:text>
                                    @if ($integration->consecutive_failures >= 2)
                                        <flux:text size="sm">{{ __(':n in a row', ['n' => $integration->consecutive_failures]) }}</flux:text>
                                    @endif
                                @else
                                    <flux:text size="sm">{{ __('Never run') }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:text size="sm">{{ $integration->next_run_at?->diffForHumans() ?? __('Manual') }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format($integration->synced_count) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format($integration->problem_count) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">
                                <flux:text class="py-6 text-center">
                                    {{ __('No integrations yet.') }}
                                    <flux:link href="{{ route('integrations.index') }}" wire:navigate>{{ __('Create one') }}</flux:link>
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        {{-- Recent problems --}}
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading>{{ __('Recent problems') }}</flux:heading>
            <flux:text size="sm">{{ __('Latest failed or partial runs') }}</flux:text>

            <div class="mt-3 space-y-3">
                @forelse ($this->recentProblems as $run)
                    @php($meta = $statusMeta[$run->status])
                    <div class="border-t border-zinc-100 pt-3 first:border-0 first:pt-0 dark:border-zinc-800" wire:key="problem-{{ $run->id }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-1.5 text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                <flux:icon :name="$meta['icon']" variant="mini" style="color: {{ $colors[$run->status] }}" />
                                @if ($run->integration)
                                    <flux:link href="{{ route('integrations.show', $run->integration_id) }}" wire:navigate>{{ $run->integration->name }}</flux:link>
                                @endif
                            </span>
                            <flux:text size="sm" class="shrink-0">{{ $run->started_at?->diffForHumans(short: true) }}</flux:text>
                        </div>
                        <flux:text size="sm" class="mt-1 line-clamp-2 break-all" title="{{ $run->error }}">{{ $run->error ?: $meta['label'] }}</flux:text>
                    </div>
                @empty
                    <flux:text class="py-6 text-center">{{ __('Nothing has failed recently.') }}</flux:text>
                @endforelse
            </div>
        </div>
    </div>
</section>
