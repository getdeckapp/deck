@props(['executions', 'showJobClass' => true, 'emptyMessage' => 'No executions found.'])

<div class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)] dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-[0_1px_2px_rgba(0,0,0,0.2),0_8px_32px_rgba(0,0,0,0.3)]">
    <table class="min-w-full divide-y divide-zinc-200 dark:divide-white/10">
        <thead class="bg-zinc-100 dark:bg-zinc-800">
            <tr>
                @if ($showJobClass)
                    <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-zinc-900 sm:pl-6 dark:text-white">Job</th>
                @endif
                <th scope="col" @class(['py-3.5 pr-3 text-left text-sm font-semibold text-zinc-900 dark:text-white', 'pl-4 sm:pl-6' => ! $showJobClass, 'px-3' => $showJobClass])>Status</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900 dark:text-white">Queue</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900 dark:text-white">Started</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-900 dark:text-white">Duration</th>
                <th scope="col" class="relative py-3.5 pr-4 pl-3 text-right text-sm font-semibold text-zinc-900 sm:pr-6 dark:text-white"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 bg-white dark:divide-white/10 dark:bg-zinc-900">
            @forelse ($executions as $execution)
                @php
                    $detailUrl = route('deck.activity.show', $execution->activityRouteParameters());
                @endphp
                <tr
                    @class([
                        'group cursor-pointer transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60',
                        'odd:bg-zinc-50/40 dark:odd:bg-zinc-800/20' => ! $execution->isLongRunning(),
                        'bg-amber-50/70 dark:bg-amber-500/10' => $execution->isLongRunning(),
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
                                class="relative z-10 font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                onclick="event.stopPropagation()"
                            >
                                {{ class_basename($execution->job_class) }}
                            </a>
                        </td>
                    @endif
                    <td @class(['py-4 pr-3 text-sm', 'pl-4 sm:pl-6' => ! $showJobClass, 'px-3' => $showJobClass])>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-deck::badge :status="$execution->status->value">{{ $execution->status->value }}</x-deck::badge>
                            @if ($execution->isLongRunning())
                                <span class="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300">Long running</span>
                            @endif
                        </div>
                        @if (! empty($execution->tags))
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($execution->tags as $tag)
                                    <span class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if ($execution->hasFailureDetails() && $execution->exception_message)
                            <p class="mt-2 max-w-xl font-mono text-xs text-red-600 dark:text-red-400">{{ Str::limit($execution->exception_message, 120) }}</p>
                        @endif
                        <p class="mt-1 font-mono text-xs text-zinc-400">{{ Str::limit($execution->uuid, 13) }}</p>
                    </td>
                    <td class="px-3 py-4 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                        {{ $execution->queue }}
                        <span class="block text-xs text-zinc-400">{{ $execution->connection }}</span>
                    </td>
                    <td class="px-3 py-4 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                        <time datetime="{{ $execution->started_at->toIso8601String() }}">{{ $execution->started_at->format('M j, H:i:s') }}</time>
                        <span class="block text-xs">{{ $execution->started_at->diffForHumans() }}</span>
                    </td>
                    <td class="px-3 py-4 text-sm whitespace-nowrap tabular-nums text-zinc-500 dark:text-zinc-400">
                        {{ $execution->formattedDuration() }}
                    </td>
                    <td class="py-4 pr-4 pl-3 text-right text-sm whitespace-nowrap sm:pr-6">
                        <div class="flex items-center justify-end gap-3">
                            <span class="text-sm font-semibold text-indigo-600 group-hover:text-indigo-500 dark:text-indigo-400 dark:group-hover:text-indigo-300">
                                Details
                                <span aria-hidden="true" class="text-indigo-400 dark:text-indigo-500">→</span>
                            </span>
                            @if ($execution->canRetry())
                                <button
                                    type="button"
                                    class="relative z-10 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-emerald-600 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-emerald-50 dark:bg-white/10 dark:text-emerald-400 dark:ring-white/10 dark:hover:bg-emerald-500/10"
                                    wire:click.stop="retryExecution(@js($execution->uuid), {{ $execution->attempt }})"
                                    wire:confirm="Retry this failed job?"
                                >
                                    Retry
                                </button>
                            @elseif ($execution->status->value === 'running')
                                <button
                                    type="button"
                                    class="relative z-10 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-red-600 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-red-50 dark:bg-white/10 dark:text-red-400 dark:ring-white/10 dark:hover:bg-red-500/10"
                                    wire:click.stop="cancelExecution(@js($execution->uuid), {{ $execution->attempt }})"
                                    wire:confirm="Cancel this running job?"
                                >
                                    Cancel
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showJobClass ? 6 : 5 }}" class="py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ $emptyMessage }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
