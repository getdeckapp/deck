<div class="space-y-8">
    <div class="rounded-xl border border-zinc-200/80 bg-white px-6 py-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900">
        <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">Jobs</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            Per-job aggregates with last status, duration, and success rate. Horizon does not keep this history.
        </p>
    </div>

    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <x-deck::stat-card label="Jobs" :value="$summary['classes']" />
        <x-deck::stat-card label="Running now" :value="$summary['running']" />
        <x-deck::stat-card label="Failed (last status)" :value="$summary['failed']" />
        <x-deck::stat-card label="Completed runs" :value="number_format($summary['successes'])" />
    </dl>

    <div class="flex flex-col gap-4 rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm lg:flex-row lg:items-end lg:justify-between dark:border-zinc-700/80 dark:bg-zinc-900">
        <x-deck::filter-tabs
            :options="['' => 'All', 'running' => 'Running', 'failed' => 'Failed', 'completed' => 'Completed', 'cancelled' => 'Cancelled']"
            :current="$status"
        />
        <div class="sm:w-72">
            <label for="deck-search" class="sr-only">Search jobs</label>
            <input
                id="deck-search"
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by job name…"
                class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500"
            >
        </div>
    </div>

    <div class="flow-root">
        <div class="-mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow ring-zinc-900/5 dark:border-zinc-700/80 dark:bg-zinc-900 dark:shadow-none dark:ring-white/10">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-white/10">
                        <thead class="bg-zinc-100 dark:bg-zinc-800">
                            <tr>
                                <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-zinc-900 sm:pl-6 dark:text-white">Job</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900 dark:text-white">Status</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900 dark:text-white">Last finished</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900 dark:text-white">Last duration</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900 dark:text-white">Success rate</th>
                                <th scope="col" class="py-3.5 pr-4 pl-3 text-right text-sm font-semibold text-zinc-900 sm:pr-6 dark:text-white">Runs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 bg-white dark:divide-white/10 dark:bg-zinc-900">
                            @forelse ($stats as $stat)
                                <tr class="transition odd:bg-zinc-50/40 hover:bg-zinc-50 dark:odd:bg-zinc-800/20 dark:hover:bg-zinc-800/60">
                                    <td class="py-4 pr-3 pl-4 text-sm sm:pl-6">
                                        <a href="{{ route('deck.classes.show', ['jobClass' => $stat->job_class]) }}" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                                            {{ class_basename($stat->job_class) }}
                                        </a>
                                        <div class="mt-1 truncate font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $stat->job_class }}</div>
                                    </td>
                                    <td class="px-3 py-4 text-sm whitespace-nowrap">
                                        @if ($stat->last_status)
                                            <x-deck::badge :status="$stat->last_status->value">{{ $stat->last_status->value }}</x-deck::badge>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                                        {{ $stat->last_finished_at?->diffForHumans() ?? '—' }}
                                    </td>
                                    <td class="px-3 py-4 text-sm whitespace-nowrap tabular-nums text-zinc-500 dark:text-zinc-400">
                                        {{ $stat->formattedLastDuration() }}
                                    </td>
                                    <td class="px-3 py-4 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                                        {{ $stat->successRate() !== null ? $stat->successRate().'%' : '—' }}
                                    </td>
                                    <td class="py-4 pr-4 pl-3 text-right text-sm whitespace-nowrap tabular-nums text-zinc-500 sm:pr-6 dark:text-zinc-400">
                                        {{ $stat->success_count }} / {{ $stat->failure_count }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                        <p class="font-medium text-zinc-900 dark:text-white">No jobs recorded yet</p>
                                        <p class="mt-1">Dispatch a queued job and run <code class="rounded bg-zinc-100 px-1 py-0.5 font-mono text-xs dark:bg-zinc-800">queue:work</code>.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($stats->hasPages())
            <div class="mt-4">{{ $stats->links() }}</div>
        @endif
    </div>
</div>
