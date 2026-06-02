@props(['status' => 'completed'])

@php
    [$dotColor, $classes] = match ($status) {
        'completed' => ['bg-emerald-500', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        'failed'    => ['bg-rose-500',    'bg-rose-50 text-rose-700 ring-rose-600/15'],
        'running'   => ['bg-blue-500',    'bg-blue-50 text-blue-700 ring-blue-700/15'],
        'cancelled' => ['bg-amber-500',   'bg-amber-50 text-amber-700 ring-amber-600/20'],
        'blocked'   => ['bg-orange-500',  'bg-orange-50 text-orange-700 ring-orange-600/20'],
        default     => ['bg-zinc-400',    'bg-zinc-50 text-zinc-600 ring-zinc-500/10'],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[12px] font-medium ring-1 ring-inset {$classes}"]) }}>
    <span class="size-1.5 rounded-full {{ $dotColor }}" aria-hidden="true"></span>
    {{ $slot }}
</span>
