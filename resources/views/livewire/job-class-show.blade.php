<div @if($shouldPoll) wire:poll.2s @endif class="space-y-8">
    <nav class="flex" aria-label="Breadcrumb">
        <ol role="list" class="flex items-center space-x-2 text-sm text-zinc-500">
            <li><a href="{{ route('deck.classes.index') }}" class="hover:text-zinc-700">Jobs</a></li>
            <li><svg class="size-5 shrink-0 text-zinc-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" /></svg></li>
            <li class="font-medium text-zinc-900">{{ class_basename($jobClass) }}</li>
        </ol>
    </nav>

    <div class="relative z-20 overflow-visible rounded-xl border border-zinc-200/80 bg-white px-6 py-5 shadow-sm">
        <div class="md:flex md:items-start md:justify-between md:gap-6">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-lg font-semibold tracking-tight text-zinc-900">{{ class_basename($jobClass) }}</h1>
                    @if ($hasRunning)
                        <span class="inline-flex items-center gap-x-1.5 rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">Live</span>
                    @endif
                    @if ($isBlocked)
                        <span class="inline-flex items-center gap-x-1.5 rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">
                            Blocked
                            @if ($blockedUntil)
                                until {{ $blockedUntil->format('M j, H:i') }}
                            @elseif ($isManualBlock)
                                (manual)
                            @endif
                        </span>
                    @endif
                </div>
                <p class="mt-1 truncate font-mono text-sm text-zinc-500">{{ $jobClass }}</p>
            </div>
            <div class="relative z-30 mt-4 flex flex-wrap items-center gap-2 md:mt-0 md:shrink-0">
                @if ($runningCount > 0)
                    <x-deck::confirm-button
                        action="cancelAllRunning"
                        title="Cancel all running jobs"
                        :message="'Cancel all '.$runningCount.' running '.str('execution')->plural($runningCount).' for this class? Jobs without the Cancellable middleware may keep running.'"
                        confirm-label="Cancel all ({{ $runningCount }})"
                        progress-label="Cancelling…"
                        tone="danger"
                        class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-red-600 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-red-50"
                    >
                        Cancel all ({{ $runningCount }})
                    </x-deck::confirm-button>
                @endif
                @if ($isBlocked)
                    <x-deck::confirm-button
                        action="unblockClass"
                        title="Unblock job"
                        message="Workers will be able to process this job class again on the next queue attempt."
                        confirm-label="Unblock"
                        progress-label="Unblocking…"
                        class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-zinc-900 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50"
                    >
                        Unblock
                    </x-deck::confirm-button>
                @else
                    <div x-data="{ open: false }" class="relative">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-amber-800 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-amber-50"
                            @click="open = ! open"
                            @click.outside="open = false"
                            :aria-expanded="open"
                            aria-haspopup="menu"
                        >
                            Block job
                            <svg class="size-4 text-amber-600/80" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            role="menu"
                            class="absolute right-0 z-50 mt-2 w-56 origin-top-right overflow-hidden rounded-lg border border-zinc-200/80 bg-white py-1 shadow-lg ring-1 ring-black/5"
                        >
                            <p class="px-4 pt-2 pb-1 text-xs font-medium tracking-wide text-zinc-500 uppercase">Block for</p>
                            <div class="mt-1 divide-y divide-zinc-100">
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="block w-full px-4 py-2.5 text-left text-sm text-zinc-700 hover:bg-zinc-50"
                                    wire:click.stop="requestConfirmation(@js('blockClass'), @js(['1h']), @js('Block for 1 hour'), @js('Running jobs will be cancelled. New dispatches are recorded as blocked and never queued until the block expires.'), @js('Block 1 hour'), @js('Blocking…'), @js('warning'))"
                                    @click="open = false"
                                >
                                    1 hour
                                </button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="block w-full px-4 py-2.5 text-left text-sm text-zinc-700 hover:bg-zinc-50"
                                    wire:click.stop="requestConfirmation(@js('blockClass'), @js(['24h']), @js('Block for 24 hours'), @js('Running jobs will be cancelled. New dispatches are recorded as blocked and never queued until the block expires.'), @js('Block 24 hours'), @js('Blocking…'), @js('warning'))"
                                    @click="open = false"
                                >
                                    24 hours
                                </button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="block w-full px-4 py-2.5 text-left text-sm text-zinc-700 hover:bg-zinc-50"
                                    wire:click.stop="requestConfirmation(@js('blockClass'), @js([]), @js('Block until manual unblock'), @js('Running jobs will be cancelled. New dispatches are recorded as blocked and never queued until you unblock this job.'), @js('Block job'), @js('Blocking…'), @js('warning'))"
                                    @click="open = false"
                                >
                                    Until manual unblock
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($stat)
        <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <x-deck::stat-card label="Last finished" :value="$stat->last_finished_at?->diffForHumans() ?? 'Never'" />
            <x-deck::stat-card label="Success rate" :value="$stat->successRate() !== null ? $stat->successRate().'%' : '—'" />
            <x-deck::stat-card label="Avg duration" :value="\TorMorten\Deck\Support\FormatDuration::format($avgDurationMs)" />
            <x-deck::stat-card label="Success / failed" :value="$stat->success_count.' / '.$stat->failure_count" />
        </dl>
    @endif

    <section class="space-y-4 rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 border-b border-zinc-100 pb-4 lg:flex-row lg:items-center lg:justify-between">
            <h2 class="text-sm font-semibold text-zinc-900">Executions</h2>
            <x-deck::filter-tabs
                :options="['' => 'All', 'running' => 'Running', 'failed' => 'Failed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'blocked' => 'Blocked']"
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

    @include('deck::partials.action-confirmation')
</div>
