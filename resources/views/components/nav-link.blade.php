@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    {{ $attributes->class([
        'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-[13.5px] font-medium transition',
        'bg-gradient-to-r from-indigo-500/[0.18] via-indigo-500/[0.10] to-transparent text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.06)] ring-1 ring-inset ring-indigo-400/20' => $active,
        'text-zinc-400 hover:bg-white/[0.04] hover:text-zinc-100' => ! $active,
    ]) }}
>
    @if ($active)
        <span
            class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-indigo-400"
            style="box-shadow: 0 0 12px 0 rgba(129,140,248,0.55);"
            aria-hidden="true"
        ></span>
    @endif
    <span @class([
        'flex size-7 shrink-0 items-center justify-center rounded-md transition',
        'bg-indigo-500/20 text-indigo-300' => $active,
        'bg-white/5 text-zinc-500 group-hover:bg-white/10 group-hover:text-zinc-300' => ! $active,
    ])>
        {{ $icon ?? '' }}
    </span>
    <span class="truncate">{{ $slot }}</span>
</a>
