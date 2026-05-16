@props(['label', 'value', 'hint' => null])

<dl {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-2xl border border-zinc-200/60 bg-white px-5 py-5 shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_16px_rgba(0,0,0,0.04)] sm:p-6 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-[0_1px_2px_rgba(0,0,0,0.2),0_4px_24px_rgba(0,0,0,0.25)]']) }}>
    <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
    <dd class="mt-2 text-3xl font-semibold tracking-tight text-zinc-900 tabular-nums dark:text-white">{{ $value }}</dd>
    @if ($hint)
        <dd class="mt-1.5 text-sm text-zinc-500 dark:text-zinc-400">{{ $hint }}</dd>
    @endif
</dl>
