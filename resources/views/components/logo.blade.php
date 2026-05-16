@props([
    'showLabel' => true,
    'size' => 'md',
    'variant' => 'light',
])

@php
    $sizes = [
        'sm' => ['icon' => 'size-8', 'text' => 'text-sm', 'sub' => 'text-[10px]'],
        'md' => ['icon' => 'size-9', 'text' => 'text-sm', 'sub' => 'text-xs'],
        'lg' => ['icon' => 'size-12', 'text' => 'text-xl', 'sub' => 'text-sm'],
    ];
    $sizeClasses = $sizes[$size] ?? $sizes['md'];
    $isDark = $variant === 'dark';
@endphp

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <svg
        class="{{ $sizeClasses['icon'] }} shrink-0"
        viewBox="0 0 32 32"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-hidden="true"
    >
        <title>Deck</title>
        <rect width="32" height="32" rx="9" class="{{ $isDark ? 'fill-indigo-500' : 'fill-indigo-600' }}" />
        <path d="M8 10.5h15" stroke="white" stroke-width="2" stroke-linecap="round" />
        <path d="M8 16h11" stroke="white" stroke-width="2" stroke-linecap="round" opacity="0.92" />
        <path d="M8 21.5h7" stroke="white" stroke-width="2" stroke-linecap="round" opacity="0.84" />
        <circle cx="22.5" cy="21.5" r="3" fill="white" />
        <circle cx="22.5" cy="21.5" r="1.25" class="{{ $isDark ? 'fill-indigo-500' : 'fill-indigo-600' }}" />
    </svg>

    @if ($showLabel)
        <div class="min-w-0">
            <span @class([
                $sizeClasses['text'],
                'block font-semibold tracking-tight',
                'text-white' => $isDark,
                'text-zinc-900' => ! $isDark,
            ])>Deck</span>
            @if ($size !== 'sm')
                <span @class([
                    $sizeClasses['sub'],
                    'block font-medium tracking-wide',
                    'text-zinc-500' => $isDark,
                    'text-zinc-500' => ! $isDark,
                ])>Queue control plane</span>
            @endif
        </div>
    @endif
</div>
