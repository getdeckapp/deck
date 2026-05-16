<x-deck::poll-container :enabled="$shouldPoll" :seconds="$pollSeconds">
    <div class="rounded-xl border border-zinc-200/80 bg-white px-6 py-5 shadow-sm">
        <h1 class="text-lg font-semibold tracking-tight text-zinc-900">Activity</h1>
        <p class="mt-2 text-sm text-zinc-600">
            Searchable execution log across all jobs. Horizon shows recent Redis jobs; Deck keeps the durable record.
        </p>
    </div>

    <div class="flex flex-col gap-4 rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm">
        <x-deck::filter-tabs
            :options="['' => 'All', 'running' => 'Running', 'failed' => 'Failed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'blocked' => 'Blocked']"
            :current="$status"
        />

        <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
            <div class="sm:w-40">
                <label for="deck-queue" class="mb-1 block text-xs font-medium text-zinc-500">Queue</label>
                <select
                    id="deck-queue"
                    wire:model.live="queue"
                    class="block w-full rounded-md border border-zinc-200 bg-white py-1.5 pr-8 pl-3 text-sm text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                >
                    <option value="">All queues</option>
                    @foreach ($queues as $queueName)
                        <option value="{{ $queueName }}">{{ $queueName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-40">
                <label for="deck-connection" class="mb-1 block text-xs font-medium text-zinc-500">Connection</label>
                <select
                    id="deck-connection"
                    wire:model.live="connection"
                    class="block w-full rounded-md border border-zinc-200 bg-white py-1.5 pr-8 pl-3 text-sm text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                >
                    <option value="">All connections</option>
                    @foreach ($connections as $connectionName)
                        <option value="{{ $connectionName }}">{{ $connectionName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-44">
                <label for="deck-tag" class="mb-1 block text-xs font-medium text-zinc-500">Tag</label>
                <select
                    id="deck-tag"
                    wire:model.live="tag"
                    class="block w-full rounded-md border border-zinc-200 bg-white py-1.5 pr-8 pl-3 text-sm text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                >
                    <option value="">All tags</option>
                    @foreach ($tags as $tagName)
                        <option value="{{ $tagName }}">{{ $tagName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-0 flex-1 sm:max-w-xs">
                <label for="deck-activity-search" class="mb-1 block text-xs font-medium text-zinc-500">Search</label>
                <input
                    id="deck-activity-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Job or UUID…"
                    class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
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

    @include('deck::partials.action-confirmation')
</x-deck::poll-container>
