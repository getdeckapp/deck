@props([
    'showLabel' => true,
    'size' => 'md',
    'variant' => 'light',
])

@php
    $sizes = [
        'sm' => ['mark' => 30, 'text' => 'text-[15px]', 'sub' => 'text-[10.5px]'],
        'md' => ['mark' => 36, 'text' => 'text-[15px]', 'sub' => 'text-[11.5px]'],
        'lg' => ['mark' => 50, 'text' => 'text-[22px]', 'sub' => 'text-[13px]'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
    $isDark = $variant === 'dark';
    $gradId = 'logo-grad-' . $variant;
    $highlightId = 'logo-hl-' . $variant;
    $gradStart = $isDark ? '#7c83f7' : '#6366f1';
    $gradEnd   = $isDark ? '#4f46e5' : '#4338ca';
    $dotFill   = $isDark ? '#4f46e5' : '#4338ca';
@endphp

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <svg
        width="{{ $s['mark'] }}"
        height="{{ $s['mark'] }}"
        viewBox="0 0 40 40"
        class="shrink-0"
        aria-hidden="true"
        role="img"
    >
        <defs>
            <linearGradient id="{{ $gradId }}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="{{ $gradStart }}" />
                <stop offset="100%" stop-color="{{ $gradEnd }}" />
            </linearGradient>
            <linearGradient id="{{ $highlightId }}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="rgba(255,255,255,0.18)" />
                <stop offset="55%" stop-color="rgba(255,255,255,0)" />
            </linearGradient>
        </defs>
        {{-- Stacked-cards monogram — three offset cards --}}
        <rect x="6" y="11" width="22" height="22" rx="4" fill="url(#{{ $gradId }})" opacity="0.35"/>
        <rect x="9" y="9" width="22" height="22" rx="4" fill="url(#{{ $gradId }})" opacity="0.6"/>
        <rect x="12" y="7" width="22" height="22" rx="4" fill="url(#{{ $gradId }})"/>
        <rect x="12" y="7" width="22" height="22" rx="4" fill="url(#{{ $highlightId }})"/>
        {{-- Control-point indicator dot --}}
        <circle cx="27" cy="21" r="3.2" fill="white"/>
        <circle cx="27" cy="21" r="1.4" fill="{{ $dotFill }}"/>
        {{-- Schedule lines --}}
        <path d="M16 13h7" stroke="white" stroke-width="1.6" stroke-linecap="round" opacity="0.92"/>
        <path d="M16 17h11" stroke="white" stroke-width="1.6" stroke-linecap="round" opacity="0.7"/>
    </svg>

    @if ($showLabel)
        <div class="min-w-0 leading-none">
            <span @class([
                'block font-semibold tracking-[-0.02em]',
                $s['text'],
                'text-white' => $isDark,
                'text-zinc-900' => ! $isDark,
            ])>Deck</span>
            @if ($size !== 'sm')
                <span @class([
                    'mt-1.5 block font-medium tracking-[0.01em] text-zinc-500',
                    $s['sub'],
                ])>Queue control plane</span>
            @endif
        </div>
    @endif
</div>
