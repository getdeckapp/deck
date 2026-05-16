<div @if($hasRunning) wire:poll.5s @endif class="space-y-8">
    <div class="rounded-xl border border-zinc-200/80 bg-white px-6 py-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900">
        <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">Activity</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            Searchable execution log across all jobs. Horizon shows recent Redis jobs; Deck keeps the durable record.
        </p>
    </div>

    <div class="flex flex-col gap-4 rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900">
        <x-deck::filter-tabs
            :options="['' => 'All', 'running' => 'Running', 'failed' => 'Failed', 'completed' => 'Completed', 'cancelled' => 'Cancelled']"
            :current="$status"
        />

        <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
            <div class="sm:w-40">
                <label for="deck-queue" class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Queue</label>
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
            <div class="sm:w-40">
                <label for="deck-connection" class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Connection</label>
                <select
                    id="deck-connection"
                    wire:model.live="connection"
                    class="block w-full rounded-md border border-zinc-200 bg-white py-1.5 pr-8 pl-3 text-sm text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                >
                    <option value="">All connections</option>
                    @foreach ($connections as $connectionName)
                        <option value="{{ $connectionName }}">{{ $connectionName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-44">
                <label for="deck-tag" class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Tag</label>
                <select
                    id="deck-tag"
                    wire:model.live="tag"
                    class="block w-full rounded-md border border-zinc-200 bg-white py-1.5 pr-8 pl-3 text-sm text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                >
                    <option value="">All tags</option>
                    @foreach ($tags as $tagName)
                        <option value="{{ $tagName }}">{{ $tagName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-0 flex-1 sm:max-w-xs">
                <label for="deck-activity-search" class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Search</label>
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

    <div class="rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900">
        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Cancel queued job</h2>
        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
            Best-effort removal from Redis by UUID. Also sets the cooperative cancel flag if the job starts later.
        </p>
        <form wire:submit="cancelPending" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="deck-pending-uuid" class="sr-only">Job UUID</label>
                <input
                    id="deck-pending-uuid"
                    type="text"
                    wire:model="pendingUuid"
                    placeholder="Job UUID from Horizon or Deck…"
                    class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-1.5 font-mono text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500"
                >
            </div>
            <button
                type="submit"
                class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-red-600 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-red-50 dark:bg-white/10 dark:text-red-400 dark:ring-white/10 dark:hover:bg-red-500/10"
            >
                Cancel queued job
            </button>
        </form>
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
