@props(['label', 'value', 'hint' => null])

<dl {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-2xl border border-zinc-200/70 bg-white pt-5 pb-4 px-5 shadow-[0_1px_0_rgba(255,255,255,0.7)_inset,0_1px_2px_rgba(15,23,42,0.04),0_8px_24px_-8px_rgba(15,23,42,0.08)]']) }}>
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-400/40 to-transparent" aria-hidden="true"></div>
    <dt class="truncate font-mono text-[12px] font-medium uppercase tracking-[0.08em] text-zinc-500">{{ $label }}</dt>
    <dd class="mt-2 text-[30px] font-semibold tracking-[-0.022em] text-zinc-900 tabular-nums leading-none">{{ $value }}</dd>
    @if ($hint)
        <dd class="mt-2 text-[13px] text-zinc-500">{{ $hint }}</dd>
    @endif
</dl>
