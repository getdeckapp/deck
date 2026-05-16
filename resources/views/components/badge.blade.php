@props(['status' => 'completed'])

@php
    $classes = match ($status) {
        'completed' => 'bg-green-50 text-green-700 ring-green-600/20',
        'failed' => 'bg-red-50 text-red-700 ring-red-600/10',
        'running' => 'bg-blue-50 text-blue-700 ring-blue-700/10',
        'cancelled' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        default => 'bg-zinc-50 text-zinc-600 ring-zinc-500/10',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {$classes}"]) }}>
    {{ $slot }}
</span>
