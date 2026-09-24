@props(['system', 'active'])

<div class="flex items-center gap-1 border-b border-zinc-200 dark:border-zinc-700">
    @foreach ([
        'endpoints' => ['label' => __('Endpoints'), 'route' => route('systems.show', $system)],
        'auth-profiles' => ['label' => __('Auth Profiles'), 'route' => route('auth-profiles.index', $system)],
        'connections' => ['label' => __('Connections'), 'route' => route('connections.index', $system)],
    ] as $key => $tab)
        <flux:link
            :href="$tab['route']"
            wire:navigate
            class="!no-underline px-3 py-2 text-sm font-medium border-b-2 -mb-px {{ $active === $key
                ? 'border-zinc-900 text-zinc-900 dark:border-white dark:text-white'
                : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            {{ $tab['label'] }}
        </flux:link>
    @endforeach
</div>
