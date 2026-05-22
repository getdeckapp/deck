@props([
    'horizonAvailable' => false,
    'horizonSummary' => null,
    'horizonWorkload' => [],
    'horizonMasters' => [],
    'queueInsights' => collect(),
    'queueAdminEnabled' => false,
])

<div class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)]">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-zinc-900">
                @if ($horizonAvailable)
                    Horizon workers & queues
                @else
                    Queue activity
                @endif
            </h2>
            <p class="mt-1 text-xs text-zinc-500">
                @if ($horizonAvailable)
                    Live workload from Horizon — pending jobs, wait times, and worker processes.
                @else
                    Busy queues from Deck’s execution log (install Horizon for live Redis workload).
                @endif
            </p>
        </div>

        @if ($horizonAvailable && $horizonSummary)
            <div class="flex flex-wrap items-center gap-2">
                <span @class([
                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
                    'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $horizonSummary['status'] === 'running',
                    'bg-amber-50 text-amber-700 ring-amber-600/20' => $horizonSummary['status'] === 'paused',
                    'bg-zinc-100 text-zinc-600 ring-zinc-500/20' => $horizonSummary['status'] === 'inactive',
                ])>
                    <span class="size-1.5 rounded-full bg-current"></span>
                    {{ ucfirst($horizonSummary['status']) }}
                </span>
                <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600 ring-1 ring-zinc-200/80">
                    {{ number_format($horizonSummary['processes']) }} processes
                </span>
                <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600 ring-1 ring-zinc-200/80">
                    {{ number_format($horizonSummary['jobs_per_minute']) }} jobs/min
                </span>
            </div>
        @endif
    </div>

    @if ($horizonAvailable)
        <div class="grid gap-6 p-5 lg:grid-cols-2">
            <div>
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Queue workload</h3>
                @if (count($horizonWorkload) === 0)
                    <p class="text-sm text-zinc-500">No queue workload reported.</p>
                @else
                    <div class="space-y-3">
                        @php $maxQueueLength = max(1, (int) collect($horizonWorkload)->max('length')); @endphp
                        @foreach ($horizonWorkload as $queue)
                            @php
                                $loadPercent = min(100, (int) round(($queue['length'] / $maxQueueLength) * 100));
                            @endphp
                            <div>
                                <div class="mb-1 flex items-center justify-between text-sm">
                                    <span class="font-medium text-zinc-900">{{ $queue['name'] }}</span>
                                    <span class="tabular-nums text-zinc-500">{{ $queue['length'] }} waiting · {{ $queue['processes'] }} workers</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-zinc-100">
                                    <div class="h-full rounded-full bg-gradient-to-r from-indigo-600 to-indigo-400" style="width: {{ max(8, $loadPercent) }}%"></div>
                                </div>
                                @if ($queue['wait'] > 0)
                                    <p class="mt-1 text-xs text-zinc-500">Est. wait ~{{ \Deck\Deck\Presentation\FormatDuration::format((int) round($queue['wait'] * 1000)) }}</p>
                                @endif
                                @if ($queueAdminEnabled && ($queue['length'] ?? 0) > 0)
                                    @php
                                        $parsedQueue = \Deck\Deck\Presentation\QueueAdmin::parseQueueKey($queue['name']);
                                    @endphp
                                    <button
                                        type="button"
                                        wire:click.stop="confirmClearQueue(@js($parsedQueue['connection']), @js($parsedQueue['queue']))"
                                        class="mt-2 text-xs font-medium text-red-600 hover:text-red-500"
                                    >
                                        Clear pending jobs
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Masters & supervisors</h3>
                @if (count($horizonMasters) === 0)
                    <p class="text-sm text-zinc-500">Horizon is not running.</p>
                @else
                    <ul class="divide-y divide-zinc-100 rounded-xl border border-zinc-200/60">
                        @foreach ($horizonMasters as $master)
                            <li class="flex items-center justify-between px-4 py-3 text-sm">
                                <div>
                                    <p class="font-medium text-zinc-900">{{ $master['name'] }}</p>
                                    <p class="text-xs text-zinc-500">{{ $master['supervisors'] }} supervisors</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium tabular-nums text-zinc-700">{{ $master['processes'] }} processes</p>
                                    <p class="text-xs capitalize text-zinc-500">{{ $master['status'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @else
        @if ($queueInsights->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-zinc-500">No queue activity recorded yet.</p>
        @else
            <div class="overflow-x-auto p-5">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wider text-zinc-500">
                            <th class="pb-3 font-medium">Queue</th>
                            <th class="pb-3 font-medium">Running</th>
                            <th class="pb-3 font-medium">Completed (1h)</th>
                            <th class="pb-3 font-medium">Avg duration (24h)</th>
                            <th class="pb-3 font-medium">Load</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($queueInsights as $queue)
                            <tr>
                                <td class="py-3 font-medium text-zinc-900">{{ $queue['queue'] }}</td>
                                <td class="py-3 tabular-nums text-zinc-600">{{ $queue['running'] }}</td>
                                <td class="py-3 tabular-nums text-zinc-600">{{ $queue['completed_last_hour'] }}</td>
                                <td class="py-3 text-zinc-600">{{ $queue['avg_duration_ms'] !== null ? \Deck\Deck\Presentation\FormatDuration::format($queue['avg_duration_ms']) : '—' }}</td>
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-24 overflow-hidden rounded-full bg-zinc-100">
                                            @php $loadWidth = min(100, $queue['load'] > 0 ? (int) round(($queue['load'] / max(1, $queueInsights->max('load'))) * 100) : 0); @endphp
                                            <div class="h-full rounded-full bg-indigo-500" style="width: {{ max(4, $loadWidth) }}%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
