<x-deck::poll-container :enabled="$shouldPoll" :seconds="$pollSeconds">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="mb-1.5 flex flex-wrap items-center gap-2">
                <p class="font-mono text-[10.5px] font-semibold uppercase tracking-[0.16em] text-indigo-600">Deck</p>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                    <span class="relative inline-flex size-1.5">
                        <span class="absolute inset-0 animate-ping rounded-full bg-emerald-500 opacity-70" aria-hidden="true"></span>
                        <span class="relative size-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    </span>
                    Live
                </span>
                @if (isset($deckCloudConnection) && $deckCloudConnection->isEnabled())
                    @include('deck::partials.cloud-connection', ['connection' => $deckCloudConnection, 'variant' => 'badge'])
                @endif
            </div>
            <h1 class="text-[28px] font-semibold tracking-[-0.022em] text-zinc-900 leading-[1.15]">Overview</h1>
            <p class="mt-1.5 text-[14px] text-zinc-500">Live status, trends, and jobs that need action.</p>
        </div>
        <a
            href="{{ route('deck.activity.index') }}"
            class="text-[13px] font-medium text-indigo-600 hover:text-indigo-500"
        >
            Full activity feed →
        </a>
    </div>

    @include('deck::partials.dashboard-status-strip', [
        'summary' => $summary,
        'busyness' => $queueBusyness,
        'unprocessedQueues' => $unprocessedQueues,
        'horizonSummary' => $horizonSummary,
        'horizonUrl' => $horizonUrl,
        'horizonAvailable' => $horizonAvailable,
        'recentFailureCount' => $recentFailureCount,
        'allClear' => $allClear,
    ])

    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <x-deck::stat-card label="Job classes" :value="$summary['classes']" />
        <x-deck::stat-card label="Total executions" :value="number_format($summary['executions'])" />
        <x-deck::stat-card label="Running now" :value="$summary['running']" />
        <x-deck::stat-card label="Failed (last run)" :value="$summary['failed']" />
        <x-deck::stat-card label="Completed runs" :value="number_format($summary['successes'])" class="col-span-2 sm:col-span-1" />
    </dl>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-deck::chart-panel
            title="Job volume"
            :subtitle="'Executions started per hour (last '.config('deck.charts.hours', 24).'h)'"
            :data="$jobVolumeChart"
            empty="No executions in this period."
            format="jobs"
        />
        <x-deck::chart-panel
            title="Average duration"
            :subtitle="'Mean run time per hour (last '.config('deck.charts.hours', 24).'h)'"
            :data="$durationChart"
            empty="No completed runs in this period."
            format="duration"
        />
    </div>

  <div class="grid gap-6 xl:grid-cols-2">
        @include('deck::partials.queue-busyness-card', ['busyness' => $queueBusyness])
        @if ($unprocessedQueues->isNotEmpty())
            @include('deck::partials.unprocessed-queues-card', ['queues' => $unprocessedQueues])
        @else
            <section class="flex flex-col justify-center overflow-hidden rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/80 px-6 py-10 text-center">
                <p class="text-sm font-medium text-zinc-700">All queues have workers</p>
                <p class="mt-1 text-sm text-zinc-500">No backlog detected on unmonitored queues.</p>
                <a href="{{ route('deck.workers.index') }}" class="mt-4 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    Workers &amp; queues →
                </a>
            </section>
        @endif
    </div>

    @if ($allClear)
        <p class="rounded-lg border border-emerald-200/80 bg-emerald-50/50 px-4 py-3 text-center text-sm text-emerald-900">
            No failures or queue issues need attention right now.
        </p>
    @endif

    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-zinc-900">Recent failures</h2>
            <a href="{{ route('deck.activity.index', ['status' => 'failed']) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                View all failures →
            </a>
        </div>
        @if ($recentFailures->isEmpty())
            <p class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-5 py-8 text-center text-sm text-zinc-500">No failed executions recently.</p>
        @else
            @include('deck::partials.execution-table', [
                'executions' => $recentFailures,
                'emptyMessage' => 'No failures.',
            ])
        @endif
    </section>

    @include('deck::partials.action-confirmation')
</x-deck::poll-container>
