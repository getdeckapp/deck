@props(['busyness'])

@php
    use TorMorten\Deck\Enums\QueueBusynessLevel;

    $level = $busyness['level'];
    $topQueues = collect($busyness['queues'])->take(4);
    $score = min(100, max(0, (int) $busyness['score']));
    $markerLeft = $score <= 0 ? '0%' : ($score >= 100 ? '100%' : "{$score}%");

    $zones = [
        ['level' => QueueBusynessLevel::Idle, 'label' => 'Idle', 'segment' => 'bg-emerald-400/70 dark:bg-emerald-500/50'],
        ['level' => QueueBusynessLevel::Light, 'label' => 'Light', 'segment' => 'bg-sky-400/70 dark:bg-sky-500/50'],
        ['level' => QueueBusynessLevel::Moderate, 'label' => 'Moderate', 'segment' => 'bg-amber-400/70 dark:bg-amber-500/50'],
        ['level' => QueueBusynessLevel::Busy, 'label' => 'Busy', 'segment' => 'bg-orange-400/70 dark:bg-orange-500/50'],
        ['level' => QueueBusynessLevel::Critical, 'label' => 'Critical', 'segment' => 'bg-rose-400/70 dark:bg-rose-500/50'],
    ];

    $accent = match ($level) {
        QueueBusynessLevel::Idle => [
            'score' => 'text-emerald-600 dark:text-emerald-400',
            'fill' => 'from-emerald-600 via-emerald-500 to-emerald-400 dark:from-emerald-500 dark:via-emerald-400 dark:to-emerald-300',
            'marker' => 'bg-emerald-500 ring-emerald-200 dark:bg-emerald-400 dark:ring-emerald-900',
            'badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300',
        ],
        QueueBusynessLevel::Light => [
            'score' => 'text-sky-600 dark:text-sky-400',
            'fill' => 'from-sky-600 via-sky-500 to-sky-400 dark:from-sky-500 dark:via-sky-400 dark:to-sky-300',
            'marker' => 'bg-sky-500 ring-sky-200 dark:bg-sky-400 dark:ring-sky-900',
            'badge' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300',
        ],
        QueueBusynessLevel::Moderate => [
            'score' => 'text-amber-700 dark:text-amber-300',
            'fill' => 'from-amber-600 via-amber-500 to-amber-400 dark:from-amber-500 dark:via-amber-400 dark:to-amber-300',
            'marker' => 'bg-amber-500 ring-amber-200 dark:bg-amber-400 dark:ring-amber-900',
            'badge' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200',
        ],
        QueueBusynessLevel::Busy => [
            'score' => 'text-orange-700 dark:text-orange-300',
            'fill' => 'from-orange-600 via-orange-500 to-orange-400 dark:from-orange-500 dark:via-orange-400 dark:to-orange-300',
            'marker' => 'bg-orange-500 ring-orange-200 dark:bg-orange-400 dark:ring-orange-900',
            'badge' => 'bg-orange-50 text-orange-800 ring-orange-600/20 dark:bg-orange-500/10 dark:text-orange-300',
        ],
        QueueBusynessLevel::Critical => [
            'score' => 'text-rose-600 dark:text-rose-400',
            'fill' => 'from-rose-600 via-rose-500 to-rose-400 dark:from-rose-500 dark:via-rose-400 dark:to-rose-300',
            'marker' => 'bg-rose-500 ring-rose-200 dark:bg-rose-400 dark:ring-rose-900',
            'badge' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300',
        ],
    };

    $queueBarClass = fn (QueueBusynessLevel $queueLevel): string => match ($queueLevel) {
        QueueBusynessLevel::Idle => 'from-emerald-600 to-emerald-400',
        QueueBusynessLevel::Light => 'from-sky-600 to-sky-400',
        QueueBusynessLevel::Moderate => 'from-amber-600 to-amber-400',
        QueueBusynessLevel::Busy => 'from-orange-600 to-orange-400',
        QueueBusynessLevel::Critical => 'from-rose-600 to-rose-400',
    };
@endphp

<section class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)] dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
        <div>
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Queue pressure</h2>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $busyness['summary'] }}</p>
        </div>
        <a href="{{ route('deck.workers.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
            Workers & queues →
        </a>
    </div>

    <div class="space-y-6 p-5">
        <div class="rounded-xl bg-zinc-50/80 p-4 dark:bg-zinc-950/60 sm:p-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div
                        role="meter"
                        aria-valuenow="{{ $score }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-label="Queue pressure {{ $score }} out of 100, {{ $busyness['label'] }}"
                    >
                        <p class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Pressure index</p>
                        <p class="mt-1 flex items-baseline gap-1.5">
                            <span class="text-4xl font-semibold tabular-nums tracking-tight {{ $accent['score'] }}">{{ $score }}</span>
                            <span class="text-sm font-medium text-zinc-400 dark:text-zinc-500">/ 100</span>
                        </p>
                    </div>

                    <span @class([
                        'mb-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                        $accent['badge'],
                    ])>
                        {{ $busyness['label'] }}
                    </span>
                </div>

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
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
                        class="relative h-3 overflow-hidden rounded-full bg-zinc-200/90 ring-1 ring-inset ring-zinc-300/50 dark:bg-zinc-800 dark:ring-zinc-700/80"
                        aria-hidden="true"
                    >
                        <div class="flex h-full">
                            @foreach ($zones as $zone)
                                <div @class([
                                    'h-full flex-1',
                                    $zone['segment'],
                                    'border-r border-white/25 dark:border-zinc-950/40' => ! $loop->last,
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
                            'text-zinc-900 dark:text-white' => $zone['level'] === $level,
                            'text-zinc-400 dark:text-zinc-500' => $zone['level'] !== $level,
                        ])>
                            {{ $zone['label'] }}
                        </span>
                    @endforeach
                </div>

                <div class="mt-1 flex justify-between text-[10px] tabular-nums text-zinc-400 dark:text-zinc-500">
                    <span>0</span>
                    <span>100</span>
                </div>
            </div>
        </div>

        <div>
            @if ($topQueues->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400">No per-queue data yet.</p>
            @else
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Busiest queues</h3>
                <div class="space-y-3">
                    @foreach ($topQueues as $queue)
                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $queue['name'] }}</span>
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">{{ $queue['detail'] }}</span>
                            </div>
                            <div class="relative h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
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
