<x-deck::poll-container :enabled="$shouldPoll" :seconds="$pollSeconds">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">Workers & queues</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-zinc-600">
                @if ($horizonAvailable)
                    Live Horizon workload, supervisors, and master processes.
                @else
                    Queue activity from Deck’s execution log. Install Horizon for live Redis workload.
                @endif
            </p>
        </div>
        @if ($horizonUrl)
            <a
                href="{{ $horizonUrl }}"
                class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-zinc-800"
            >
                Open Horizon
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
            </a>
        @endif
    </div>

    @include('deck::partials.unprocessed-queues-card', ['queues' => $unprocessedQueues])

    @include('deck::partials.workload-panel', [
        'horizonAvailable' => $horizonAvailable,
        'horizonSummary' => $horizonSummary,
        'horizonWorkload' => $horizonWorkload,
        'horizonMasters' => $horizonMasters,
        'queueInsights' => $queueInsights,
    ])

    @if ($horizonAvailable && count($horizonSupervisors) > 0)
        <section class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)]">
            <div class="border-b border-zinc-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-zinc-900">Supervisors</h2>
                <p class="mt-1 text-xs text-zinc-500">Each supervisor manages worker processes for one or more queues.</p>
            </div>
            <div class="overflow-x-auto p-5">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wider text-zinc-500">
                            <th class="pb-3 font-medium">Supervisor</th>
                            <th class="pb-3 font-medium">Master</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium">Processes</th>
                            <th class="pb-3 font-medium">Queues</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($horizonSupervisors as $supervisor)
                            <tr>
                                <td class="py-3 font-medium text-zinc-900">{{ $supervisor['name'] }}</td>
                                <td class="py-3 text-zinc-600">{{ $supervisor['master'] }}</td>
                                <td class="py-3 capitalize text-zinc-600">{{ $supervisor['status'] }}</td>
                                <td class="py-3 tabular-nums text-zinc-600">{{ $supervisor['processes'] }}</td>
                                <td class="py-3 text-zinc-600">
                                    @foreach ($supervisor['queues'] as $queue)
                                        <span class="mr-2 inline-flex rounded-md bg-zinc-100 px-2 py-0.5 text-xs">
                                            {{ $queue['name'] }} ({{ $queue['processes'] }})
                                        </span>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-deck::poll-container>
