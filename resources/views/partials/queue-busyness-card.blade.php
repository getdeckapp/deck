@props(['busyness'])

@php
    use Deck\Deck\Enums\QueueBusynessLevel;

    $level = $busyness['level'];
    $topQueues = collect($busyness['queues'])->take(4);
    $score = min(100, max(0, (int) $busyness['score']));
    $markerLeft = $score <= 0 ? '0%' : ($score >= 100 ? '100%' : "{$score}%");

    $zones = [
        ['level' => QueueBusynessLevel::Idle, 'label' => 'Idle', 'segment' => 'bg-emerald-400/70'],
        ['level' => QueueBusynessLevel::Light, 'label' => 'Light', 'segment' => 'bg-sky-400/70'],
        ['level' => QueueBusynessLevel::Moderate, 'label' => 'Moderate', 'segment' => 'bg-amber-400/70'],
        ['level' => QueueBusynessLevel::Busy, 'label' => 'Busy', 'segment' => 'bg-orange-400/70'],
        ['level' => QueueBusynessLevel::Critical, 'label' => 'Critical', 'segment' => 'bg-rose-400/70'],
    ];

    $accent = match ($level) {
        QueueBusynessLevel::Idle => [
            'score' => 'text-emerald-600',
            'fill' => 'from-emerald-600 via-emerald-500 to-emerald-400',
            'marker' => 'bg-emerald-500 ring-emerald-200',
            'badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        ],
        QueueBusynessLevel::Light => [
            'score' => 'text-sky-600',
            'fill' => 'from-sky-600 via-sky-500 to-sky-400',
            'marker' => 'bg-sky-500 ring-sky-200',
            'badge' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        ],
        QueueBusynessLevel::Moderate => [
            'score' => 'text-amber-700',
            'fill' => 'from-amber-600 via-amber-500 to-amber-400',
            'marker' => 'bg-amber-500 ring-amber-200',
            'badge' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        ],
        QueueBusynessLevel::Busy => [
            'score' => 'text-orange-700',
            'fill' => 'from-orange-600 via-orange-500 to-orange-400',
            'marker' => 'bg-orange-500 ring-orange-200',
            'badge' => 'bg-orange-50 text-orange-800 ring-orange-600/20',
        ],
        QueueBusynessLevel::Critical => [
            'score' => 'text-rose-600',
            'fill' => 'from-rose-600 via-rose-500 to-rose-400',
            'marker' => 'bg-rose-500 ring-rose-200',
            'badge' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        ],
    };

    $queueBarClass = fn (QueueBusynessLevel $queueLevel): string => match ($queueLevel) {
        QueueBusynessLevel::Idle => 'from-emerald-600 to-emerald-400',
        QueueBusynessLevel::Light => 'from-sky-600 to-sky-400',
        QueueBusynessLevel::Moderate => 'from-amber-600 to-amber-400',
        QueueBusynessLevel::Busy => 'from-orange-600 to-orange-400',
        QueueBusynessLevel::Critical => 'from-rose-600 to-rose-400',
    };

    $queueRowBg = fn (QueueBusynessLevel $queueLevel): string => match ($queueLevel) {
        QueueBusynessLevel::Idle, QueueBusynessLevel::Light => '',
        QueueBusynessLevel::Moderate => 'rounded-lg bg-amber-50/50 px-2 -mx-2',
        QueueBusynessLevel::Busy => 'rounded-lg bg-orange-50/60 px-2 -mx-2',
        QueueBusynessLevel::Critical => 'rounded-lg bg-rose-50/70 px-2 -mx-2',
    };

    $queueNameClass = fn (QueueBusynessLevel $queueLevel): string => match ($queueLevel) {
        QueueBusynessLevel::Idle, QueueBusynessLevel::Light => 'text-sm font-medium text-zinc-900',
        QueueBusynessLevel::Moderate => 'text-sm font-semibold text-amber-900',
        QueueBusynessLevel::Busy => 'text-sm font-semibold text-orange-900',
        QueueBusynessLevel::Critical => 'text-sm font-bold text-rose-900',
    };

    $queueDetailClass = fn (QueueBusynessLevel $queueLevel): string => match ($queueLevel) {
        QueueBusynessLevel::Idle, QueueBusynessLevel::Light => 'text-xs text-zinc-500',
        QueueBusynessLevel::Moderate => 'text-xs font-medium text-amber-700',
        QueueBusynessLevel::Busy => 'text-xs font-semibold text-orange-700',
        QueueBusynessLevel::Critical => 'text-xs font-semibold text-rose-700',
    };

    $queueBarHeight = fn (QueueBusynessLevel $queueLevel): string => match ($queueLevel) {
        QueueBusynessLevel::Idle, QueueBusynessLevel::Light => 'h-1.5',
        QueueBusynessLevel::Moderate => 'h-2',
        QueueBusynessLevel::Busy => 'h-2.5',
        QueueBusynessLevel::Critical => 'h-3',
    };
@endphp

<section class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)]">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-zinc-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-zinc-900">Queue pressure</h2>
            <p class="mt-1 text-xs text-zinc-500">{{ $busyness['summary'] }}</p>
        </div>
        <a href="{{ route('deck.workers.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
            Workers & queues →
        </a>
    </div>

    <div class="space-y-6 p-5">
        <div class="rounded-xl bg-zinc-50/80 p-4 sm:p-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div
                        role="meter"
                        aria-valuenow="{{ $score }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-label="Queue pressure {{ $score }} out of 100, {{ $busyness['label'] }}"
                    >
                        <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">Pressure index</p>
                        <p class="mt-1 flex items-baseline gap-1.5">
                            <span class="text-4xl font-semibold tabular-nums tracking-tight {{ $accent['score'] }}">{{ $score }}</span>
                            <span class="text-sm font-medium text-zinc-400">/ 100</span>
                        </p>
                    </div>

                    <span @class([
                        'mb-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                        $accent['badge'],
                    ])>
                        {{ $busyness['label'] }}
                    </span>
                </div>

                <p class="text-xs text-zinc-500">
                    @if ($busyness['source'] === 'horizon')
                        Live from Horizon
                    @else
                        Estimated from Deck history
                    @endif
                </p>
            </div>

            <div class="mt-5">
                <div class="relative pt-1">
                    <div
                        class="relative h-3 overflow-hidden rounded-full bg-zinc-200/90 ring-1 ring-inset ring-zinc-300/50"
                        aria-hidden="true"
                    >
                        <div class="flex h-full">
                            @foreach ($zones as $zone)
                                <div @class([
                                    'h-full flex-1',
                                    $zone['segment'],
                                    'border-r border-white/25' => ! $loop->last,
                                ])></div>
                            @endforeach
                        </div>

                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 rounded-full bg-gradient-to-r opacity-90 {{ $accent['fill'] }}"
                            style="width: {{ $score }}%"
                        ></div>
                    </div>

                    <div
                        class="pointer-events-none absolute top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 shadow-sm {{ $accent['marker'] }}"
                        style="left: {{ $markerLeft }}"
                        aria-hidden="true"
                    ></div>
                </div>

                <div class="mt-3 grid grid-cols-5 gap-1 text-center">
                    @foreach ($zones as $zone)
                        <span @class([
                            'truncate text-[10px] font-medium leading-tight sm:text-xs',
                            'text-zinc-900' => $zone['level'] === $level,
                            'text-zinc-400' => $zone['level'] !== $level,
                        ])>
                            {{ $zone['label'] }}
                        </span>
                    @endforeach
                </div>

                <div class="mt-1 flex justify-between text-[10px] tabular-nums text-zinc-400">
                    <span>0</span>
                    <span>100</span>
                </div>
            </div>
        </div>

        <div>
            @if ($topQueues->isEmpty())
                <p class="text-sm text-zinc-500">No per-queue data yet.</p>
            @else
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Busiest queues</h3>
                <div class="space-y-3">
                    @foreach ($topQueues as $queue)
                        <div class="{{ $queueRowBg($queue['level']) }}">
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <span class="flex items-center gap-1.5 {{ $queueNameClass($queue['level']) }}">
                                    @if (in_array($queue['level'], [\Deck\Deck\Enums\QueueBusynessLevel::Busy, \Deck\Deck\Enums\QueueBusynessLevel::Critical]))
                                        <svg class="size-3.5 shrink-0 {{ $queue['level'] === \Deck\Deck\Enums\QueueBusynessLevel::Critical ? 'text-rose-500' : 'text-orange-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                        </svg>
                                    @endif
                                    {{ $queue['name'] }}
                                </span>
                                <span class="shrink-0 {{ $queueDetailClass($queue['level']) }}">{{ $queue['detail'] }}</span>
                            </div>
                            <div class="relative {{ $queueBarHeight($queue['level']) }} overflow-hidden rounded-full bg-zinc-100">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r {{ $queueBarClass($queue['level']) }}"
                                    style="width: {{ max(6, $queue['score']) }}%"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
