<div @if($hasRunning) wire:poll.5s @endif class="space-y-8">
    <nav class="flex" aria-label="Breadcrumb">
        <ol role="list" class="flex items-center space-x-2 text-sm text-zinc-500 dark:text-zinc-400">
            <li><a href="{{ route('deck.classes.index') }}" class="hover:text-zinc-700 dark:hover:text-zinc-300">Job classes</a></li>
            <li><svg class="size-5 shrink-0 text-zinc-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" /></svg></li>
            <li class="font-medium text-zinc-900 dark:text-white">{{ class_basename($jobClass) }}</li>
        </ol>
    </nav>

    <div class="rounded-xl border border-zinc-200/80 bg-white px-6 py-5 shadow-sm md:flex md:items-center md:justify-between dark:border-zinc-700/80 dark:bg-zinc-900">
        <div class="min-w-0 flex-1">
            <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">{{ class_basename($jobClass) }}</h1>
            <p class="mt-1 truncate font-mono text-sm text-zinc-500 dark:text-zinc-400">{{ $jobClass }}</p>
        </div>
        @if ($hasRunning)
            <span class="mt-4 inline-flex items-center gap-x-1.5 rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 md:mt-0 dark:bg-blue-500/10 dark:text-blue-400">Live</span>
        @endif
    </div>

    @if ($stat)
        <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <x-deck::stat-card label="Last finished" :value="$stat->last_finished_at?->diffForHumans() ?? 'Never'" />
            <x-deck::stat-card label="Success rate" :value="$stat->successRate() !== null ? $stat->successRate().'%' : '—'" />
            <x-deck::stat-card label="Avg duration" :value="\TorMorten\Deck\Support\FormatDuration::format($avgDurationMs)" />
            <x-deck::stat-card label="Success / failed" :value="$stat->success_count.' / '.$stat->failure_count" />
        </dl>
    @endif

    <section class="space-y-4 rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 border-b border-zinc-100 pb-4 lg:flex-row lg:items-center lg:justify-between dark:border-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Executions</h2>
            <x-deck::filter-tabs
                :options="['' => 'All', 'running' => 'Running', 'failed' => 'Failed', 'completed' => 'Completed', 'cancelled' => 'Cancelled']"
                :current="$status"
            />
        </div>

        <div class="flow-root">
            <div class="-mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                    @include('deck::partials.execution-table', [
                        'executions' => $executions,
                        'showJobClass' => false,
                        'emptyMessage' => 'No executions recorded for this class.',
                    ])
                </div>
            </div>
        </div>

        @if ($executions->hasPages())
            <div>{{ $executions->links() }}</div>
        @endif
    </section>
</div>
