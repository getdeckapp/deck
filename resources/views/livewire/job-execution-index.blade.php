<x-deck::poll-container :enabled="$shouldPoll" :seconds="$pollSeconds">
    <div>
        <p class="mb-1.5 font-mono text-[10.5px] font-semibold uppercase tracking-[0.16em] text-amber-600">Activity</p>
        <h1 class="text-[28px] font-semibold tracking-[-0.022em] text-zinc-900 leading-[1.15]">Execution Log</h1>
        <p class="mt-1.5 text-[14px] text-zinc-500">
            Searchable execution log across all jobs. Horizon shows recent Redis jobs; Deck keeps the durable record.
        </p>
    </div>

    <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200/70 bg-white p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
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
                    class="block w-full rounded-md border border-zinc-200 bg-white py-1.5 pr-8 pl-3 text-sm text-zinc-900 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
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
                    class="block w-full rounded-md border border-zinc-200 bg-white py-1.5 pr-8 pl-3 text-sm text-zinc-900 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
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
                    class="block w-full rounded-md border border-zinc-200 bg-white py-1.5 pr-8 pl-3 text-sm text-zinc-900 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
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
                    placeholder="Job, UUID, group, exception…"
                    class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                >
            </div>
            <div class="sm:w-48">
                <label for="deck-dispatch-group" class="mb-1 block text-xs font-medium text-zinc-500">Dispatch group</label>
                <input
                    id="deck-dispatch-group"
                    type="text"
                    wire:model.live.debounce.300ms="dispatch_group"
                    placeholder="Group ID"
                    class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                >
            </div>
            <div class="sm:w-48">
                <label for="deck-batch-id" class="mb-1 block text-xs font-medium text-zinc-500">Batch ID</label>
                <input
                    id="deck-batch-id"
                    type="text"
                    wire:model.live.debounce.300ms="batch_id"
                    placeholder="Batch UUID"
                    class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-1.5 font-mono text-xs text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                >
            </div>
        </div>
    </div>

    @include('deck::partials.execution-table', ['executions' => $executions])

    @if ($executions->hasPages())
        <div>{{ $executions->links() }}</div>
    @endif

    @include('deck::partials.action-confirmation')
</x-deck::poll-container>
