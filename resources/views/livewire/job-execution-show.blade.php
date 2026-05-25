<x-deck::poll-container :enabled="$shouldPoll" :seconds="$pollSeconds">
    <nav class="flex" aria-label="Breadcrumb">
        <ol role="list" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-zinc-500">
            <li><a href="{{ route('deck.activity.index') }}" class="hover:text-zinc-700">Activity</a></li>
            <li aria-hidden="true"><svg class="size-5 shrink-0 text-zinc-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" /></svg></li>
            <li><a href="{{ route('deck.classes.show', ['jobClass' => $execution->job_class]) }}" class="hover:text-zinc-700">{{ class_basename($execution->job_class) }}</a></li>
            <li aria-hidden="true"><svg class="size-5 shrink-0 text-zinc-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" /></svg></li>
            <li class="font-medium text-zinc-900">Execution</li>
        </ol>
    </nav>

    @if ($execution->isCancellationPending())
        @include('deck::partials.cancellation-pending-banner')
    @endif

    @php($progress = $execution->progress())

    @if ($progress)
        <section class="overflow-hidden rounded-2xl border border-indigo-200/80 bg-white shadow-sm">
            <div class="border-b border-indigo-100 bg-indigo-50/40 px-5 py-4">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-sm font-semibold text-indigo-900">Progress</h2>
                    <span class="text-sm font-semibold tabular-nums text-indigo-700">{{ $progress->percent }}%</span>
                </div>
                @if ($progress->message)
                    <p class="mt-1 text-sm text-indigo-800/90">{{ $progress->message }}</p>
                @endif
            </div>
            <div class="px-5 py-4">
                <div class="h-2 overflow-hidden rounded-full bg-indigo-100">
                    <div class="h-full rounded-full bg-indigo-600 transition-all duration-500" style="width: {{ max(2, $progress->percent) }}%"></div>
                </div>
            </div>
        </section>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">{{ class_basename($execution->job_class) }}</h1>
                <x-deck::badge :status="$execution->status->value">{{ $execution->status->value }}</x-deck::badge>
                @if ($execution->isCancellationPending())
                    @include('deck::partials.cancellation-pending-badge')
                @endif
                @if ($execution->isLongRunning())
                    <span class="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">Long running</span>
                @endif
            </div>
            <p class="mt-2 truncate font-mono text-sm text-zinc-500">{{ $execution->job_class }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($execution->canRetry())
                <x-deck::confirm-button
                    action="retryExecution"
                    :params="[$execution->uuid, $execution->attempt]"
                    title="Retry failed job"
                    message="This queues the job again via Horizon, the failed-job store, or a fresh dispatch when possible."
                    confirm-label="Retry job"
                    progress-label="Retrying…"
                    class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-indigo-600 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-indigo-50"
                >
                    Retry job
                </x-deck::confirm-button>
            @else
                @include('deck::partials.execution-cancel-actions', [
                    'execution' => $execution,
                    'class' => 'rounded-lg bg-white px-3 py-2 text-sm font-semibold text-red-600 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-red-50',
                ])
            @endif
        </div>
    </div>

    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <x-deck::stat-card label="Queue wait" :value="$execution->formattedWait()" />
        <x-deck::stat-card label="Run time" :value="$execution->formattedDuration()" />
        <x-deck::stat-card label="Queue" :value="$execution->queue" />
        <x-deck::stat-card label="Connection" :value="$execution->connection" />
        <x-deck::stat-card label="Attempt" :value="(string) $execution->attempt" />
        <x-deck::stat-card label="Dispatched" :value="$execution->dispatched_at?->format('M j, H:i:s') ?? '—'" />
        <x-deck::stat-card label="Started" :value="$execution->started_at->format('M j, H:i:s')" />
        <x-deck::stat-card label="Finished" :value="$execution->finished_at?->format('M j, H:i:s') ?? '—'" />
    </dl>

    @include('deck::partials.execution-observability', [
        'execution' => $execution,
        'parentExecution' => $parentExecution,
    ])

    @include('deck::partials.execution-related-table', [
        'executions' => $groupExecutions,
        'title' => 'Same dispatch group',
        'description' => 'Other executions stamped with this group.',
    ])

    @include('deck::partials.execution-related-table', [
        'executions' => $childExecutions,
        'title' => 'Dispatched jobs',
        'description' => 'Jobs queued while this execution was running.',
    ])

    <section class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)]">
        <div class="border-b border-zinc-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-zinc-900">Execution details</h2>
        </div>
        <dl class="divide-y divide-zinc-100">
            <div class="grid gap-1 px-5 py-4 sm:grid-cols-3">
                <dt class="text-xs font-medium uppercase tracking-wider text-zinc-500">UUID</dt>
                <dd class="sm:col-span-2 font-mono text-sm text-zinc-900">{{ $execution->uuid }}</dd>
            </div>
            <div class="grid gap-1 px-5 py-4 sm:grid-cols-3">
                <dt class="text-xs font-medium uppercase tracking-wider text-zinc-500">Started</dt>
                <dd class="sm:col-span-2 text-sm text-zinc-900">
                    <time datetime="{{ $execution->started_at->toIso8601String() }}">{{ $execution->started_at->format('M j, Y H:i:s') }}</time>
                    <span class="text-zinc-500">({{ $execution->started_at->diffForHumans() }})</span>
                </dd>
            </div>
            @if ($execution->finished_at)
                <div class="grid gap-1 px-5 py-4 sm:grid-cols-3">
                    <dt class="text-xs font-medium uppercase tracking-wider text-zinc-500">Finished</dt>
                    <dd class="sm:col-span-2 text-sm text-zinc-900">
                        <time datetime="{{ $execution->finished_at->toIso8601String() }}">{{ $execution->finished_at->format('M j, Y H:i:s') }}</time>
                        <span class="text-zinc-500">({{ $execution->finished_at->diffForHumans() }})</span>
                    </dd>
                </div>
            @endif
            @if (! empty($execution->tags))
                <div class="grid gap-2 px-5 py-4 sm:grid-cols-3">
                    <dt class="text-xs font-medium uppercase tracking-wider text-zinc-500">Tags</dt>
                    <dd class="flex flex-wrap gap-1.5 sm:col-span-2">
                        @foreach ($execution->tags as $tag)
                            <span class="rounded bg-zinc-100 px-2 py-0.5 font-mono text-xs text-zinc-700">{{ $tag }}</span>
                        @endforeach
                    </dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($execution->hasFailureDetails())
        @php($horizonFailedUrl = $execution->horizonFailedJobUrl())
        <section class="overflow-hidden rounded-2xl border border-red-200/80 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)]">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-red-100 bg-red-50/50 px-5 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-red-800">Failure</h2>
                    @if ($execution->exception_class)
                        <p class="mt-1 font-mono text-sm text-red-700">{{ $execution->exception_class }}</p>
                    @endif
                </div>
                @if ($horizonFailedUrl)
                    <a
                        href="{{ $horizonFailedUrl }}"
                        class="shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-500"
                    >
                        View in Horizon
                    </a>
                @endif
            </div>
            <div class="space-y-5 p-5">
                @if ($execution->exception_message)
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Message</h3>
                        <p class="mt-2 font-mono text-sm leading-relaxed whitespace-pre-wrap text-red-700">{{ $execution->exception_message }}</p>
                    </div>
                @endif
                @if ($execution->exception_trace)
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Stack trace</h3>
                        <pre class="mt-2 max-h-[32rem] overflow-auto rounded-xl border border-zinc-200 bg-zinc-950 p-4 font-mono text-xs leading-relaxed text-zinc-100">{{ $execution->exception_trace }}</pre>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if (config('deck.store_context') && ! empty($execution->context))
        <section class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)]">
            <div class="border-b border-zinc-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-zinc-900">Context</h2>
            </div>
            <pre class="max-h-96 overflow-auto p-5 font-mono text-xs text-zinc-700">{{ json_encode($execution->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </section>
    @endif

    @include('deck::partials.action-confirmation')
</x-deck::poll-container>
