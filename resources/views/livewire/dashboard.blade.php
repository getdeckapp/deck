<div @if($hasRunning) wire:poll.5s @endif class="space-y-8">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">Overview</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
            Durable job history, cancellation, and class-level insight — everything Horizon keeps only in Redis.
        </p>
    </div>

    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5">
        <x-deck::stat-card label="Job classes" :value="$summary['classes']" />
        <x-deck::stat-card label="Total executions" :value="number_format($summary['executions'])" />
        <x-deck::stat-card label="Running now" :value="$summary['running']" />
        <x-deck::stat-card label="Failed (last run)" :value="$summary['failed']" />
        <x-deck::stat-card label="Completed runs" :value="number_format($summary['successes'])" />
    </dl>

    <div class="grid gap-8 xl:grid-cols-2">
        <section class="rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-100 pb-4 dark:border-zinc-800">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Running now</h2>
                <a href="{{ route('deck.activity.index', ['status' => 'running']) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">View all</a>
            </div>
            <div class="mt-4">
                @if ($running->isEmpty())
                    <p class="rounded-lg border border-dashed border-zinc-200 bg-zinc-50 p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400">No jobs are running.</p>
                @else
                    @include('deck::partials.execution-table', ['executions' => $running, 'emptyMessage' => 'No running jobs.'])
                @endif
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-100 pb-4 dark:border-zinc-800">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Recent failures</h2>
                <a href="{{ route('deck.activity.index', ['status' => 'failed']) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">View all</a>
            </div>
            <div class="mt-4">
                @if ($recentFailures->isEmpty())
                    <p class="rounded-lg border border-dashed border-zinc-200 bg-zinc-50 p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400">No recent failures.</p>
                @else
                    @include('deck::partials.execution-table', ['executions' => $recentFailures, 'emptyMessage' => 'No failures.'])
                @endif
            </div>
        </section>
    </div>

    <section class="rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900">
        <div class="flex items-center justify-between border-b border-zinc-100 pb-4 dark:border-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Latest activity</h2>
            <a href="{{ route('deck.activity.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Open activity feed</a>
        </div>
        <div class="mt-4">
            @include('deck::partials.execution-table', ['executions' => $recentActivity, 'emptyMessage' => 'No activity yet.'])
        </div>
    </section>
</div>
