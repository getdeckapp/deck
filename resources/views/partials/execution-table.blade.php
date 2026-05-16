@props(['executions', 'showJobClass' => true, 'emptyMessage' => 'No executions found.'])

<div class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)]">
    <table class="min-w-full divide-y divide-zinc-200">
        <thead class="bg-zinc-100">
            <tr>
                @if ($showJobClass)
                    <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-zinc-900 sm:pl-6">Job</th>
                @endif
                <th scope="col" @class(['py-3.5 pr-3 text-left text-sm font-semibold text-zinc-900', 'pl-4 sm:pl-6' => ! $showJobClass, 'px-3' => $showJobClass])>Status</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900">Queue</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900">Started</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900">Duration</th>
                <th scope="col" class="relative py-3.5 pr-4 pl-3 text-right text-sm font-semibold text-zinc-900 sm:pr-6"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 bg-white">
            @forelse ($executions as $execution)
                @php
                    $detailUrl = route('deck.activity.show', $execution->activityRouteParameters());
                @endphp
                <tr
                    @class([
                        'group cursor-pointer transition hover:bg-zinc-50',
                        'odd:bg-zinc-50/40' => ! $execution->isLongRunning(),
                        'bg-amber-50/70' => $execution->isLongRunning(),
                    ])
                    role="link"
                    tabindex="0"
                    data-href="{{ $detailUrl }}"
                    onclick="window.location.assign(this.dataset.href)"
                    onkeydown="if (event.key === 'Enter') { event.preventDefault(); window.location.assign(this.dataset.href); }"
                >
                    @if ($showJobClass)
                        <td class="py-4 pr-3 pl-4 text-sm sm:pl-6">
                            <a
                                href="{{ route('deck.classes.show', ['jobClass' => $execution->job_class]) }}"
                                class="relative z-10 font-medium text-indigo-600 hover:text-indigo-500"
                                onclick="event.stopPropagation()"
                            >
                                {{ class_basename($execution->job_class) }}
                            </a>
                        </td>
                    @endif
                    <td @class(['py-4 pr-3 text-sm', 'pl-4 sm:pl-6' => ! $showJobClass, 'px-3' => $showJobClass])>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-deck::badge :status="$execution->status->value">{{ $execution->status->value }}</x-deck::badge>
                            @if ($execution->isCancellationPending())
                                @include('deck::partials.cancellation-pending-badge')
                            @endif
                            @if ($execution->isLongRunning())
                                <span class="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">Long running</span>
                            @endif
                        </div>
                        @if (! empty($execution->tags))
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($execution->tags as $tag)
                                    <span class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-zinc-600">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if ($execution->hasFailureDetails() && $execution->exception_message)
                            <p class="mt-2 max-w-xl font-mono text-xs text-red-600">{{ Str::limit($execution->exception_message, 120) }}</p>
                        @endif
                        <p class="mt-1 font-mono text-xs text-zinc-400">{{ Str::limit($execution->uuid, 13) }}</p>
                    </td>
                    <td class="px-3 py-4 text-sm whitespace-nowrap text-zinc-500">
                        {{ $execution->queue }}
                        <span class="block text-xs text-zinc-400">{{ $execution->connection }}</span>
                    </td>
                    <td class="px-3 py-4 text-sm whitespace-nowrap text-zinc-500">
                        <time datetime="{{ $execution->started_at->toIso8601String() }}">{{ $execution->started_at->format('M j, H:i:s') }}</time>
                        <span class="block text-xs">{{ $execution->started_at->diffForHumans() }}</span>
                    </td>
                    <td class="px-3 py-4 text-sm whitespace-nowrap tabular-nums text-zinc-500">
                        {{ $execution->formattedDuration() }}
                    </td>
                    <td class="py-4 pr-4 pl-3 text-right text-sm whitespace-nowrap sm:pr-6">
                        <div class="flex items-center justify-end gap-4">
                            @if ($execution->canRetry())
                                <x-deck::confirm-button
                                    action="retryExecution"
                                    :params="[$execution->uuid, $execution->attempt]"
                                    title="Retry failed job"
                                    message="This queues the job again via Horizon, the failed-job store, or a fresh dispatch when possible."
                                    confirm-label="Retry"
                                    progress-label="Retrying…"
                                    class="relative z-10 text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                                >
                                    Retry
                                </x-deck::confirm-button>
                            @else
                                @include('deck::partials.execution-cancel-actions', [
                                    'execution' => $execution,
                                ])
                            @endif
                            <span class="text-sm font-semibold text-indigo-600 group-hover:text-indigo-500">
                                Details
                                <span aria-hidden="true" class="text-indigo-400">→</span>
                            </span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showJobClass ? 6 : 5 }}" class="py-12 text-center text-sm text-zinc-500">{{ $emptyMessage }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
