<div class="space-y-8">
    <div class="rounded-xl border border-zinc-200/80 bg-white px-6 py-5 shadow-sm">
        <h1 class="text-lg font-semibold tracking-tight text-zinc-900">Jobs</h1>
        <p class="mt-2 text-sm text-zinc-600">
            Per-job aggregates with last status, duration, and success rate. Horizon does not keep this history.
        </p>
    </div>

    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <x-deck::stat-card label="Jobs" :value="$summary['classes']" />
        <x-deck::stat-card label="Running now" :value="$summary['running']" />
        <x-deck::stat-card label="Failed (last status)" :value="$summary['failed']" />
        <x-deck::stat-card label="Completed runs" :value="number_format($summary['successes'])" />
    </dl>

    <div class="flex flex-col gap-4 rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm lg:flex-row lg:items-end lg:justify-between">
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
                class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
            >
        </div>
    </div>

    <div class="flow-root">
        <div class="-mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow ring-zinc-900/5">
                    <table class="min-w-full divide-y divide-zinc-200">
                        <thead class="bg-zinc-100">
                            <tr>
                                <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-zinc-900 sm:pl-6">Job</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900">Status</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900">Last finished</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900">Last duration</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900">Success rate</th>
                                <th scope="col" class="py-3.5 pr-4 pl-3 text-right text-sm font-semibold text-zinc-900 sm:pr-6">Runs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 bg-white">
                            @forelse ($stats as $stat)
                                <tr class="transition odd:bg-zinc-50/40 hover:bg-zinc-50">
                                    <td class="py-4 pr-3 pl-4 text-sm sm:pl-6">
                                        <a href="{{ route('deck.classes.show', ['jobClass' => $stat->job_class]) }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                                            {{ class_basename($stat->job_class) }}
                                        </a>
                                        <div class="mt-1 truncate font-mono text-xs text-zinc-500">{{ $stat->job_class }}</div>
                                    </td>
                                    <td class="px-3 py-4 text-sm whitespace-nowrap">
                                        @if ($stat->last_status)
                                            <x-deck::badge :status="$stat->last_status->value">{{ $stat->last_status->value }}</x-deck::badge>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4 text-sm whitespace-nowrap text-zinc-500">
                                        {{ $stat->last_finished_at?->diffForHumans() ?? '—' }}
                                    </td>
                                    <td class="px-3 py-4 text-sm whitespace-nowrap tabular-nums text-zinc-500">
                                        {{ $stat->formattedLastDuration() }}
                                    </td>
                                    <td class="px-3 py-4 text-sm whitespace-nowrap text-zinc-500">
                                        {{ $stat->successRate() !== null ? $stat->successRate().'%' : '—' }}
                                    </td>
                                    <td class="py-4 pr-4 pl-3 text-right text-sm whitespace-nowrap tabular-nums text-zinc-500 sm:pr-6">
                                        {{ $stat->success_count }} / {{ $stat->failure_count }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-sm text-zinc-500">
                                        <p class="font-medium text-zinc-900">No jobs recorded yet</p>
                                        <p class="mt-1">Dispatch a queued job and run <code class="rounded bg-zinc-100 px-1 py-0.5 font-mono text-xs">queue:work</code>.</p>
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
