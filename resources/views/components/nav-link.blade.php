@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    {{ $attributes->class([
        'group relative flex items-center gap-3 rounded-lg py-2.5 pr-3 text-sm font-medium transition duration-150',
        'border-l-2 border-indigo-400 bg-gradient-to-r from-indigo-500/15 via-indigo-500/5 to-transparent pl-3.5 text-white' => $active,
        'border-l-2 border-transparent pl-3.5 text-zinc-400 hover:bg-white/[0.04] hover:text-zinc-100' => ! $active,
    ]) }}
>
    <span @class([
        'flex size-8 shrink-0 items-center justify-center rounded-lg transition',
        'bg-indigo-500/20 text-indigo-300' => $active,
        'bg-white/5 text-zinc-500 group-hover:bg-white/10 group-hover:text-zinc-300' => ! $active,
    ])>
        {{ $icon ?? '' }}
    </span>
    <span class="truncate">{{ $slot }}</span>
</a>
