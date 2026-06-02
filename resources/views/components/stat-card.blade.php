@props([
    'label',
    'value',
    'hint' => null,
    'sparkline' => null,
    'delta' => null,
    'deltaPositive' => null,
    'variant' => 'default',
])

@php
    $isDanger = $variant === 'danger';
    $topBar = $isDanger
        ? 'from-transparent via-rose-400/50 to-transparent'
        : 'from-transparent via-amber-400/40 to-transparent';
    $valueClass = $isDanger ? 'text-rose-600' : 'text-zinc-900';
    $deltaClass = match(true) {
        $deltaPositive === true  => 'text-rose-600',
        $deltaPositive === false => 'text-emerald-600',
        default                  => 'text-zinc-500',
    };
    $baseClass = $isDanger
        ? 'border-rose-200/70 bg-rose-50/20'
        : 'border-zinc-200/70 bg-white';
@endphp

<dl {{ $attributes->merge(['class' => "relative overflow-hidden rounded-2xl border {$baseClass} pt-5 pb-4 px-5 shadow-[0_1px_0_rgba(255,255,255,0.7)_inset,0_1px_2px_rgba(15,23,42,0.04),0_8px_24px_-8px_rgba(15,23,42,0.08)]"]) }}>
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r {{ $topBar }}" aria-hidden="true"></div>

    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <dt class="truncate font-mono text-[12px] font-medium uppercase tracking-[0.08em] text-zinc-500">{{ $label }}</dt>
            <dd class="mt-2 text-[30px] font-semibold tracking-[-0.022em] {{ $valueClass }} tabular-nums leading-none">{{ $value }}</dd>

            @if ($delta !== null)
                <dd class="mt-1.5 text-[12px] font-medium tabular-nums {{ $deltaClass }}">{{ $delta }}</dd>
            @elseif ($hint)
                <dd class="mt-2 text-[13px] text-zinc-500">{{ $hint }}</dd>
            @endif
        </div>

        @if ($sparkline !== null && count($sparkline) > 1)
            @php
                $sparkMax = max(1, max($sparkline));
                $sw = 72; $sh = 28;
                $n = count($sparkline);
                $pts = [];
                for ($i = 0; $i < $n; $i++) {
                    $px = round($i / ($n - 1) * $sw, 1);
                    $py = round($sh - ($sparkline[$i] / $sparkMax) * ($sh - 4) - 2, 1);
                    $pts[] = "{$px},{$py}";
                }
                $polyline = implode(' ', $pts);
                $area = $polyline . " {$sw},{$sh} 0,{$sh}";
                $lineColor  = $isDanger ? '#f43f5e' : '#f59e0b';
                $fillColor  = $isDanger ? 'rgba(244,63,94,0.10)' : 'rgba(245, 158, 11,0.10)';
            @endphp
            <dd class="mt-2 shrink-0 self-start" aria-hidden="true">
                <svg width="{{ $sw }}" height="{{ $sh }}" viewBox="0 0 {{ $sw }} {{ $sh }}" preserveAspectRatio="none">
                    <polygon points="{{ $area }}" fill="{{ $fillColor }}" />
                    <polyline points="{{ $polyline }}" fill="none" stroke="{{ $lineColor }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </dd>
        @endif
    </div>
</dl>
