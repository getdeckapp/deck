<div @if($shouldPoll) wire:poll.10s @endif class="space-y-8">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">Overview</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
            Durable job history, cancellation, and per-job insight — everything Horizon keeps only in Redis.
        </p>
    </div>

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

    @include('deck::partials.unprocessed-queues-card', ['queues' => $unprocessedQueues])

    @include('deck::partials.queue-busyness-card', ['busyness' => $queueBusyness])

    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5">
        <x-deck::stat-card label="Jobs" :value="$summary['classes']" />
        <x-deck::stat-card label="Total executions" :value="number_format($summary['executions'])" />
        <x-deck::stat-card label="Running now" :value="$summary['running']" />
        <x-deck::stat-card label="Failed (last run)" :value="$summary['failed']" />
        <x-deck::stat-card label="Completed runs" :value="number_format($summary['successes'])" />
    </dl>

    @if ($tags->isNotEmpty())
        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-200/80 bg-white p-4 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900">
            <div class="sm:w-56">
                <label for="deck-overview-tag" class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Filter activity by tag</label>
                <select
                    id="deck-overview-tag"
                    wire:model.live="tag"
                    class="block w-full rounded-md border border-zinc-200 bg-white py-1.5 pr-8 pl-3 text-sm text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                >
                    <option value="">All tags</option>
                    @foreach ($tags as $tagName)
                        <option value="{{ $tagName }}">{{ $tagName }}</option>
                    @endforeach
                </select>
            </div>
            @if ($tag !== '')
                <a href="{{ route('deck.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Clear tag filter</a>
            @endif
        </div>
    @endif

    <div class="grid gap-8 xl:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Running now</h2>
                <a href="{{ route('deck.activity.index', ['status' => 'running']) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">View all</a>
            </div>
            <div class="p-5">
                @if ($running->isEmpty())
                    <p class="rounded-lg border border-dashed border-zinc-200 bg-zinc-50 p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400">No jobs are running.</p>
                @else
                    @include('deck::partials.execution-table', ['executions' => $running, 'emptyMessage' => 'No running jobs.'])
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Recent failures</h2>
                <a href="{{ route('deck.activity.index', ['status' => 'failed']) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">View all</a>
            </div>
            <div class="p-5">
                @if ($recentFailures->isEmpty())
                    <p class="rounded-lg border border-dashed border-zinc-200 bg-zinc-50 p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400">No recent failures.</p>
                @else
                    @include('deck::partials.execution-table', ['executions' => $recentFailures, 'emptyMessage' => 'No failures.'])
                @endif
            </div>
        </section>
    </div>

    <section class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)] dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Latest activity</h2>
            <a href="{{ route('deck.activity.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Open activity feed</a>
        </div>
        <div class="p-5">
            @include('deck::partials.execution-table', ['executions' => $recentActivity, 'emptyMessage' => 'No activity yet.'])
        </div>
    </section>

    @include('deck::partials.action-confirmation')
</div>
