<div @if($hasRunning) wire:poll.5s @endif class="space-y-8">
    <nav class="flex" aria-label="Breadcrumb">
        <ol role="list" class="flex items-center space-x-2 text-sm text-zinc-500 dark:text-zinc-400">
            <li><a href="{{ route('deck.classes.index') }}" class="hover:text-zinc-700 dark:hover:text-zinc-300">Jobs</a></li>
            <li><svg class="size-5 shrink-0 text-zinc-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" /></svg></li>
            <li class="font-medium text-zinc-900 dark:text-white">{{ class_basename($jobClass) }}</li>
        </ol>
    </nav>

    <div class="overflow-visible rounded-xl border border-zinc-200/80 bg-white px-6 py-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900">
        <div class="md:flex md:items-start md:justify-between md:gap-6">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">{{ class_basename($jobClass) }}</h1>
                    @if ($hasRunning)
                        <span class="inline-flex items-center gap-x-1.5 rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-500/10 dark:text-blue-400">Live</span>
                    @endif
                    @if ($isBlocked)
                        <span class="inline-flex items-center gap-x-1.5 rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300">
                            Blocked
                            @if ($blockedUntil)
                                until {{ $blockedUntil->format('M j, H:i') }}
                            @elseif ($isManualBlock)
                                (manual)
                            @endif
                        </span>
                    @endif
                </div>
                <p class="mt-1 truncate font-mono text-sm text-zinc-500 dark:text-zinc-400">{{ $jobClass }}</p>
            </div>
            <div class="relative z-30 mt-4 flex flex-wrap items-center gap-2 md:mt-0 md:shrink-0">
                @if ($runningCount > 0)
                    <button
                        type="button"
                        class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-red-600 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-red-50 dark:bg-white/10 dark:text-red-400 dark:ring-white/10 dark:hover:bg-red-500/10"
                        wire:click="cancelAllRunning"
                        wire:confirm="Cancel all {{ $runningCount }} running {{ str('execution')->plural($runningCount) }} for this class? Jobs without the Cancellable middleware may keep running."
                    >
                        Cancel all ({{ $runningCount }})
                    </button>
                @endif
                @if ($isBlocked)
                    <button
                        type="button"
                        class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-zinc-900 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50 dark:bg-white/10 dark:text-white dark:ring-white/10 dark:hover:bg-white/15"
                        wire:click="unblockClass"
                        wire:confirm="Unblock this job so workers can process it again?"
                    >
                        Unblock
                    </button>
                @else
                    <div x-data="{ open: false }" class="relative">
                        <button
                            type="button"
                            class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-amber-800 shadow-xs ring-1 ring-inset ring-amber-600/30 hover:bg-amber-50 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30 dark:hover:bg-amber-500/20"
                            @click="open = ! open"
                            @click.outside="open = false"
                        >
                            Block job
                        </button>
                        <div
                            x-show="open"
                            x-cloak
                            class="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-lg border border-zinc-200/80 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                        >
                            <button
                                type="button"
                                class="block w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-700/80"
                                wire:click="blockClass('1h')"
                                @click="open = false"
                                wire:confirm="Block this job for 1 hour? Running jobs will be cancelled."
                            >
                                1 hour
                            </button>
                            <button
                                type="button"
                                class="block w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-700/80"
                                wire:click="blockClass('24h')"
                                @click="open = false"
                                wire:confirm="Block this job for 24 hours? Running jobs will be cancelled."
                            >
                                24 hours
                            </button>
                            <button
                                type="button"
                                class="block w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-700/80"
                                wire:click="blockClass"
                                @click="open = false"
                                wire:confirm="Block this job until you unblock it? Running jobs will be cancelled."
                            >
                                Until manual unblock
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($stat)
        <dl class="relative z-0 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
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
