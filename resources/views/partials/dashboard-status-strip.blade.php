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
    use TorMorten\Deck\Enums\QueueBusynessLevel;

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

<section class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-100 px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            @if ($allClear)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-600/20">
                    <span class="size-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    All clear
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-900 ring-1 ring-inset ring-amber-600/20">
                    <span class="size-2 rounded-full bg-amber-500" aria-hidden="true"></span>
                    Needs attention
                </span>
            @endif
        </div>

        @if ($horizonUrl)
            <a
                href="{{ $horizonUrl }}"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500"
            >
                Open Horizon
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
            </a>
        @endif
    </div>

    <dl class="grid grid-cols-2 gap-px bg-zinc-200/80 sm:grid-cols-3 lg:grid-cols-5">
        <div class="bg-white px-4 py-4">
            <dt class="text-xs font-medium text-zinc-500">Running</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900">{{ $summary['running'] }}</dd>
        </div>
        <div class="bg-white px-4 py-4">
            <dt class="text-xs font-medium text-zinc-500">Recent failures</dt>
            <dd @class(['mt-1 text-2xl font-semibold tabular-nums', $recentFailureCount > 0 ? 'text-rose-600' : 'text-zinc-900'])>
                {{ $recentFailureCount }}
            </dd>
        </div>
        <div class="bg-white px-4 py-4">
            <dt class="text-xs font-medium text-zinc-500">Queues w/o workers</dt>
            <dd @class(['mt-1 text-2xl font-semibold tabular-nums', $unprocessedCount > 0 ? 'text-amber-700' : 'text-zinc-900'])>
                {{ $unprocessedCount }}
            </dd>
        </div>
        <div class="bg-white px-4 py-4">
            <dt class="text-xs font-medium text-zinc-500">Queue pressure</dt>
            <dd class="mt-1 flex items-baseline gap-2">
                <span @class(['text-2xl font-semibold tabular-nums', $pressureTone])>{{ $pressureScore }}</span>
                <span class="text-xs font-medium text-zinc-500">{{ $busyness['label'] }}</span>
            </dd>
        </div>
        <div class="col-span-2 bg-white px-4 py-4 sm:col-span-1">
            <dt class="text-xs font-medium text-zinc-500">Horizon</dt>
            <dd class="mt-1">
                @if ($horizonAvailable && $horizonStatus)
                    <span @class(['text-sm font-semibold capitalize', $horizonTone])>{{ $horizonStatus }}</span>
                    @if (isset($horizonSummary['processes']))
                        <span class="mt-0.5 block text-xs text-zinc-500">
                            {{ $horizonSummary['processes'] }} processes
                            @if (($horizonSummary['jobs_per_minute'] ?? 0) > 0)
                                · {{ $horizonSummary['jobs_per_minute'] }}/min
                            @endif
                        </span>
                    @endif
                @elseif ($horizonAvailable)
                    <span class="text-sm font-semibold text-zinc-500">Unavailable</span>
                @else
                    <span class="text-sm text-zinc-500">Not installed</span>
                @endif
            </dd>
        </div>
    </dl>
</section>
