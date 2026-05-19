@props([
    'summary',
    'busyness',
    'unprocessedQueues',
    'horizonSummary',
    'horizonUrl',
    'horizonAvailable',
    'recentFailureCount',
    'allClear',
])

@php
    use Deck\Deck\Enums\QueueBusynessLevel;

    $unprocessedCount = $unprocessedQueues->count();
    $pressureLevel = $busyness['level'];
    $pressureScore = min(100, max(0, (int) $busyness['score']));

    $pressureTone = match ($pressureLevel) {
        QueueBusynessLevel::Critical, QueueBusynessLevel::Busy => 'text-rose-600',
        QueueBusynessLevel::Moderate => 'text-amber-700',
        QueueBusynessLevel::Light => 'text-sky-600',
        default => 'text-emerald-600',
    };

    $horizonStatus = $horizonSummary['status'] ?? null;
    $horizonTone = match ($horizonStatus) {
        'running' => 'text-emerald-600',
        'paused' => 'text-amber-700',
        'inactive' => 'text-rose-600',
        default => 'text-zinc-500',
    };
@endphp

<section class="relative overflow-hidden rounded-2xl border border-zinc-200/70 bg-gradient-to-br from-white via-zinc-50/40 to-indigo-50/30 shadow-[0_1px_0_rgba(255,255,255,0.7)_inset,0_1px_2px_rgba(15,23,42,0.04),0_8px_24px_-8px_rgba(15,23,42,0.08)]">
    {{-- Corner radial glow --}}
    <div class="pointer-events-none absolute right-0 top-0 size-[280px] opacity-40" style="background: radial-gradient(circle at 100% 0%, rgba(99,102,241,0.12) 0%, transparent 60%);" aria-hidden="true"></div>

    <div class="relative flex flex-wrap items-center justify-between gap-4 border-b border-zinc-100/80 px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            @if ($allClear)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-[12.5px] font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-600/20">
                    <span class="relative inline-flex size-2">
                        <span class="absolute inset-0 animate-ping rounded-full bg-emerald-400 opacity-60" aria-hidden="true"></span>
                        <span class="relative size-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    </span>
                    All clear
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-[12.5px] font-semibold text-amber-900 ring-1 ring-inset ring-amber-600/20">
                    <span class="relative inline-flex size-2">
                        <span class="absolute inset-0 animate-ping rounded-full bg-amber-400 opacity-60" aria-hidden="true"></span>
                        <span class="relative size-2 rounded-full bg-amber-500" aria-hidden="true"></span>
                    </span>
                    Needs attention
                </span>
            @endif
        </div>

        @if ($horizonUrl)
            <a
                href="{{ $horizonUrl }}"
                class="inline-flex items-center gap-1.5 text-[13px] font-medium text-indigo-600 hover:text-indigo-500"
            >
                Open Horizon
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
            </a>
        @endif
    </div>

    <dl class="relative grid grid-cols-2 divide-x divide-zinc-100/80 sm:grid-cols-3 lg:grid-cols-5">
        <div class="px-5 py-4">
            <dt class="font-mono text-[10.5px] font-medium uppercase tracking-[0.10em] text-zinc-500">Running</dt>
            <dd class="mt-1.5 text-[28px] font-semibold tabular-nums leading-none tracking-[-0.022em] text-zinc-900">{{ $summary['running'] }}</dd>
        </div>
        <div class="px-5 py-4">
            <dt class="font-mono text-[10.5px] font-medium uppercase tracking-[0.10em] text-zinc-500">Recent failures</dt>
            <dd @class(['mt-1.5 text-[28px] font-semibold tabular-nums leading-none tracking-[-0.022em]', $recentFailureCount > 0 ? 'text-rose-600' : 'text-zinc-900'])>
                {{ $recentFailureCount }}
            </dd>
        </div>
        <div class="px-5 py-4">
            <dt class="font-mono text-[10.5px] font-medium uppercase tracking-[0.10em] text-zinc-500">Queues w/o workers</dt>
            <dd @class(['mt-1.5 text-[28px] font-semibold tabular-nums leading-none tracking-[-0.022em]', $unprocessedCount > 0 ? 'text-amber-700' : 'text-zinc-900'])>
                {{ $unprocessedCount }}
            </dd>
        </div>
        <div class="px-5 py-4">
            <dt class="font-mono text-[10.5px] font-medium uppercase tracking-[0.10em] text-zinc-500">Queue pressure</dt>
            <dd class="mt-1.5 flex items-baseline gap-2">
                <span @class(['text-[28px] font-semibold tabular-nums leading-none tracking-[-0.022em]', $pressureTone])>{{ $pressureScore }}</span>
                <span class="font-mono text-[10.5px] font-medium text-zinc-500">{{ $busyness['label'] }}</span>
            </dd>
        </div>
        <div class="col-span-2 px-5 py-4 sm:col-span-1">
            <dt class="font-mono text-[10.5px] font-medium uppercase tracking-[0.10em] text-zinc-500">Horizon</dt>
            <dd class="mt-1.5">
                @if ($horizonAvailable && $horizonStatus)
                    <span @class(['text-[14px] font-semibold capitalize leading-none', $horizonTone])>{{ $horizonStatus }}</span>
                    @if (isset($horizonSummary['processes']))
                        <span class="mt-1 block font-mono text-[11px] text-zinc-500">
                            {{ $horizonSummary['processes'] }} processes
                            @if (($horizonSummary['jobs_per_minute'] ?? 0) > 0)
                                · {{ $horizonSummary['jobs_per_minute'] }}/min
                            @endif
                        </span>
                    @endif
                @elseif ($horizonAvailable)
                    <span class="text-[14px] font-semibold leading-none text-zinc-500">Unavailable</span>
                @else
                    <span class="text-[13px] text-zinc-500">Not installed</span>
                @endif
            </dd>
        </div>
    </dl>
</section>
