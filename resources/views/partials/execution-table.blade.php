@props(['executions', 'showJobClass' => true, 'emptyMessage' => 'No executions found.'])
@php
    use Illuminate\Support\Str;
@endphp
<div class="overflow-hidden rounded-2xl border border-zinc-200/70 bg-white shadow-[0_1px_0_rgba(255,255,255,0.7)_inset,0_1px_2px_rgba(15,23,42,0.04),0_12px_32px_-8px_rgba(15,23,42,0.10)]">
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead>
                <tr class="border-b border-zinc-100 bg-zinc-50/60">
                    @if ($showJobClass)
                        <th scope="col" class="py-3 pr-3 pl-5 text-left font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Job</th>
                    @endif
                    <th scope="col" @class(['py-3 pr-3 text-left font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500', 'px-3' => $showJobClass, 'pl-5' => ! $showJobClass])>
                        Status
                    </th>
                    <th scope="col" class="px-3 py-3 text-left font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Queue</th>
                    <th scope="col" class="px-3 py-3 text-left font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Started</th>
                    <th scope="col" class="px-3 py-3 text-right font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Duration</th>
                    <th scope="col" class="relative py-3 pr-5 pl-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 bg-white">
            @forelse ($executions as $execution)
                @php
                    $detailUrl = route('deck.activity.show', $execution->activityRouteParameters());
                @endphp
                <tr
                    @class([
                        'group relative cursor-pointer transition hover:bg-indigo-50/30',
                        'bg-amber-50/40' => $execution->isLongRunning(),
                    ])
                    role="link"
                    tabindex="0"
                    data-href="{{ $detailUrl }}"
                    onclick="window.location.assign(this.dataset.href)"
                    onkeydown="if (event.key === 'Enter') { event.preventDefault(); window.location.assign(this.dataset.href); }"
                >
                    <td class="pointer-events-none absolute left-0 top-0 h-full w-[2px] bg-indigo-500 opacity-0 transition group-hover:opacity-100" aria-hidden="true"></td>
                    @if ($showJobClass)
                        <td class="py-3.5 pr-3 pl-5">
                            <a
                                href="{{ route('deck.classes.show', ['jobClass' => $execution->job_class]) }}"
                                class="relative z-10 text-[13.5px] font-semibold text-zinc-900 hover:text-indigo-600"
                                onclick="event.stopPropagation()"
                            >
                                {{ class_basename($execution->job_class) }}
                            </a>
                            <div class="mt-0.5 truncate font-mono text-[11px] text-zinc-500">{{ $execution->job_class }}</div>
                        </td>
                    @endif
                    <td @class(['py-3.5 pr-3', 'px-3' => $showJobClass, 'pl-5' => ! $showJobClass])>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <x-deck::badge :status="$execution->status->value">{{ $execution->status->value }}</x-deck::badge>
                            @if ($execution->isCancellationPending())
                                @include('deck::partials.cancellation-pending-badge')
                            @endif
                            @if ($execution->isLongRunning())
                                <span class="rounded-md bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">long running</span>
                            @endif
                            @if ($execution->attempt > 1)
                                <span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-[11px] font-medium text-zinc-700 ring-1 ring-inset ring-zinc-200">Attempt {{ $execution->attempt }}</span>
                                <span class="rounded-md bg-indigo-50 px-1.5 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">Retry #{{ $execution->attempt - 1 }}</span>
                            @endif
                        </div>
                        @if (! empty($execution->tags))
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($execution->tags as $tag)
                                    <span class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-600">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if ($execution->hasFailureDetails() && $execution->exception_message)
                            <p class="mt-2 max-w-md font-mono text-[11px] text-rose-700">{{ Str::limit($execution->exception_message, 90) }}</p>
                        @endif
                        <p class="mt-1.5 font-mono text-[10.5px] text-zinc-400">{{ Str::limit($execution->uuid, 13) }}</p>
                    </td>
                    <td class="px-3 py-3.5 whitespace-nowrap">
                        <p class="text-[13px] text-zinc-900">{{ $execution->queue }}</p>
                        <p class="mt-0.5 font-mono text-[11px] text-zinc-500">{{ $execution->connection }}</p>
                    </td>
                    <td class="px-3 py-3.5 whitespace-nowrap">
                        <p class="text-[13px] text-zinc-900">{{ $execution->started_at->diffForHumans() }}</p>
                        <p class="mt-0.5 font-mono text-[11px] text-zinc-500 tabular-nums">{{ $execution->started_at->format('M j, H:i:s') }}</p>
                    </td>
                    <td class="px-3 py-3.5 whitespace-nowrap text-right">
                        <p class="font-mono text-[13px] font-semibold tabular-nums text-zinc-900">{{ $execution->formattedDuration() }}</p>
                    </td>
                    <td class="py-3.5 pr-5 pl-3 whitespace-nowrap text-right">
                        <div class="flex items-center justify-end gap-3">
                            @if ($execution->canRetry())
                                <x-deck::confirm-button
                                    action="retryExecution"
                                    :params="[$execution->uuid, $execution->attempt]"
                                    title="Retry failed job"
                                    message="This queues the job again via Horizon, the failed-job store, or a fresh dispatch when possible."
                                    confirm-label="Retry"
                                    progress-label="Retrying…"
                                    class="relative z-10 text-[13px] font-semibold text-indigo-600 hover:text-indigo-500"
                                >
                                    Retry
                                </x-deck::confirm-button>
                            @else
                                @include('deck::partials.execution-cancel-actions', [
                                    'execution' => $execution,
                                ])
                            @endif
                            <span class="text-zinc-300 transition group-hover:text-indigo-400" aria-hidden="true">
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                            </span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showJobClass ? 6 : 5 }}" class="py-14 text-center text-[13px] text-zinc-500">{{ $emptyMessage }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
