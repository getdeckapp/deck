@props(['options', 'current' => ''])

<div class="inline-flex flex-wrap gap-1 rounded-xl bg-zinc-100/80 p-1 ring-1 ring-zinc-200/60 dark:bg-zinc-900/80 dark:ring-zinc-800">
    @foreach ($options as $value => $label)
        <button
            type="button"
            wire:click="setStatus('{{ $value }}')"
            @class([
                'rounded-lg px-3.5 py-1.5 text-sm font-medium transition',
                'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-200/80 dark:bg-zinc-800 dark:text-white dark:ring-zinc-700' => $current === $value,
                'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200' => $current !== $value,
            ])
        >
            {{ $label }}
        </button>
    @endforeach
</div>
