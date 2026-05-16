<div @if($hasRunning) wire:poll.5s @endif class="space-y-8">
    <div class="rounded-xl border border-zinc-200/80 bg-white px-6 py-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900">
        <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">Activity</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            Searchable execution log across all jobs. Horizon shows recent Redis jobs; Deck keeps the durable record.
        </p>
    </div>

    <div class="flex flex-col gap-4 rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm lg:flex-row lg:items-end lg:justify-between dark:border-zinc-700/80 dark:bg-zinc-900">
        <x-deck::filter-tabs
            :options="['' => 'All', 'running' => 'Running', 'failed' => 'Failed', 'completed' => 'Completed', 'cancelled' => 'Cancelled']"
            :current="$status"
        />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="sm:w-40">
                <label for="deck-queue" class="sr-only">Queue</label>
                <select
                    id="deck-queue"
                    wire:model.live="queue"
                    class="block w-full rounded-md border border-zinc-200 bg-white py-1.5 pr-8 pl-3 text-sm text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                >
                    <option value="">All queues</option>
                    @foreach ($queues as $queueName)
                        <option value="{{ $queueName }}">{{ $queueName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-72">
                <label for="deck-activity-search" class="sr-only">Search</label>
                <input
                    id="deck-activity-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Job or UUID…"
                    class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500"
                >
            </div>
        </div>
    </div>

    <div class="flow-root">
        <div class="-mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                @include('deck::partials.execution-table', ['executions' => $executions])
            </div>
        </div>
    </div>

    @if ($executions->hasPages())
        <div>{{ $executions->links() }}</div>
    @endif
</div>
