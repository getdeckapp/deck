@props(['busyness'])

@php
    use TorMorten\Deck\Enums\QueueBusynessLevel;

    $level = $busyness['level'];
    $topQueues = collect($busyness['queues'])->take(4);
    $score = min(100, max(0, (int) $busyness['score']));
    $ringRadius = 42;
    $ringCircumference = round(2 * M_PI * $ringRadius, 2);
    $ringOffset = round($ringCircumference * (1 - ($score / 100)), 2);

    $ringTrackClass = 'text-zinc-200/80 dark:text-zinc-700/80';
    $ringProgressClass = match ($level) {
        QueueBusynessLevel::Idle => 'text-emerald-500 dark:text-emerald-400',
        QueueBusynessLevel::Light => 'text-sky-500 dark:text-sky-400',
        QueueBusynessLevel::Moderate => 'text-amber-500 dark:text-amber-400',
        QueueBusynessLevel::Busy => 'text-orange-500 dark:text-orange-400',
        QueueBusynessLevel::Critical => 'text-rose-500 dark:text-rose-400',
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

    <div class="grid gap-8 p-5 lg:grid-cols-[10.5rem_1fr] lg:items-center">
        <div class="flex flex-col items-center text-center">
            <div class="relative size-36">
                <svg viewBox="0 0 100 100" class="size-full -rotate-90" aria-hidden="true">
                    <circle
                        cx="50"
                        cy="50"
                        r="{{ $ringRadius }}"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="7"
                        class="{{ $ringTrackClass }}"
                    />
                    <circle
                        cx="50"
                        cy="50"
                        r="{{ $ringRadius }}"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="7"
                        stroke-linecap="round"
                        class="{{ $ringProgressClass }} transition-[stroke-dashoffset] duration-500"
                        stroke-dasharray="{{ $ringCircumference }}"
                        stroke-dashoffset="{{ $ringOffset }}"
                    />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-3xl font-semibold tabular-nums leading-none text-zinc-900 dark:text-white">{{ $score }}</span>
                    <span class="mt-0.5 text-sm font-medium text-zinc-500 dark:text-zinc-400">/ 100</span>
                </div>
            </div>

            <span @class([
                'mt-4 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300' => $level === QueueBusynessLevel::Idle,
                'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300' => $level === QueueBusynessLevel::Light,
                'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200' => $level === QueueBusynessLevel::Moderate,
                'bg-orange-50 text-orange-800 ring-orange-600/20 dark:bg-orange-500/10 dark:text-orange-300' => $level === QueueBusynessLevel::Busy,
                'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300' => $level === QueueBusynessLevel::Critical,
            ])>
                {{ $busyness['label'] }}
            </span>

            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                @if ($busyness['source'] === 'horizon')
                    Live from Horizon
                @else
                    Estimated from Deck history
                @endif
            </p>
        </div>

        <div>
            @if ($topQueues->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400">No per-queue data yet.</p>
            @else
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Busiest queues</h3>
                <div class="space-y-3">
                    @foreach ($topQueues as $queue)
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $queue['name'] }}</span>
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">{{ $queue['detail'] }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-indigo-600 to-indigo-400 transition-all"
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
