<div class="space-y-8">
    <div>
        <p class="mb-1.5 font-mono text-[10.5px] font-semibold uppercase tracking-[0.16em] text-amber-600">Jobs</p>
        <h1 class="text-[28px] font-semibold tracking-[-0.022em] text-zinc-900 leading-[1.15]">Job Classes</h1>
        <p class="mt-1.5 text-[14px] text-zinc-500">
            Per-job aggregates with last status, duration, and success rate. Horizon does not keep this history.
        </p>
    </div>

    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <x-deck::stat-card label="Jobs" :value="$summary['classes']" />
        <x-deck::stat-card label="Running now" :value="$summary['running']" />
        <x-deck::stat-card label="Failed (last status)" :value="$summary['failed']" />
        <x-deck::stat-card label="Completed runs" :value="number_format($summary['successes'])" />
    </dl>

    <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200/70 bg-white p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] lg:flex-row lg:items-end lg:justify-between">
        <x-deck::filter-tabs
            :options="['' => 'All', 'running' => 'Running', 'failed' => 'Failed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'blocked' => 'Blocked']"
            :current="$status"
        />
        <div class="sm:w-72">
            <label for="deck-search" class="sr-only">Search jobs</label>
            <input
                id="deck-search"
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by job name…"
                class="block w-full rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-[13px] text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
            >
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200/70 bg-white shadow-[0_1px_0_rgba(255,255,255,0.7)_inset,0_1px_2px_rgba(15,23,42,0.04),0_12px_32px_-8px_rgba(15,23,42,0.10)]">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/60">
                        <th scope="col" class="py-3 pr-3 pl-5 text-left font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Job</th>
                        <th scope="col" class="px-3 py-3 text-left font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Status</th>
                        <th scope="col" class="px-3 py-3 text-left font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Last finished</th>
                        <th scope="col" class="px-3 py-3 text-left font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Last duration</th>
                        <th scope="col" class="px-3 py-3 text-left font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Success rate</th>
                        <th scope="col" class="py-3 pr-5 pl-3 text-right font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Runs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white">
                    @forelse ($stats as $stat)
                        <tr class="group relative cursor-pointer transition hover:bg-amber-50/30">
                            <td class="relative py-3.5 pr-3 pl-5 before:pointer-events-none before:absolute before:inset-y-0 before:left-0 before:w-[2px] before:bg-amber-500 before:opacity-0 before:transition group-hover:before:opacity-100">
                                <a href="{{ route('deck.classes.show', ['jobClass' => $stat->job_class]) }}" class="text-[13.5px] font-semibold text-zinc-900 hover:text-amber-600">
                                    {{ class_basename($stat->job_class) }}
                                </a>
                                <div class="mt-0.5 truncate font-mono text-[11px] text-zinc-500">{{ $stat->job_class }}</div>
                            </td>
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                @if ($stat->last_status)
                                    <x-deck::badge :status="$stat->last_status->value">{{ $stat->last_status->value }}</x-deck::badge>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 text-[13px] whitespace-nowrap text-zinc-500">
                                {{ $stat->last_finished_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="px-3 py-3.5 text-[13px] whitespace-nowrap tabular-nums text-zinc-500">
                                {{ $stat->formattedLastDuration() }}
                            </td>
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                @php $rate = $stat->successRate(); @endphp
                                @if ($rate !== null)
                                    <div class="flex items-center gap-2">
                                        <div class="h-1 w-16 overflow-hidden rounded-full bg-zinc-100">
                                            <div
                                                class="{{ $rate >= 90 ? 'bg-emerald-500' : ($rate >= 70 ? 'bg-amber-400' : 'bg-rose-500') }} h-full rounded-full transition-all"
                                                style="width: {{ $rate }}%"
                                            ></div>
                                        </div>
                                        <span class="font-mono text-[12px] tabular-nums text-zinc-600">{{ $rate }}%</span>
                                    </div>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 pr-5 pl-3 text-right text-[13px] whitespace-nowrap tabular-nums text-zinc-500">
                                {{ $stat->success_count }} / {{ $stat->failure_count }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-14 text-center text-[13px] text-zinc-500">
                                <p class="font-medium text-zinc-900">No jobs recorded yet</p>
                                <p class="mt-1">Dispatch a queued job and run <code class="rounded bg-zinc-100 px-1 py-0.5 font-mono text-[11px]">queue:work</code>.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($stats->hasPages())
            <div class="border-t border-zinc-100 px-5 py-4">{{ $stats->links() }}</div>
        @endif
    </div>
</div>
