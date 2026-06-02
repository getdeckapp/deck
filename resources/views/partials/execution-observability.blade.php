@props(['execution', 'parentExecution' => null])

@php
    use Deck\Deck\Presentation\ExecutionObservability;
@endphp

@if (ExecutionObservability::hasObservability($execution))
    <section class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)]">
        <div class="border-b border-zinc-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-zinc-900">Dispatch context</h2>
        </div>

        <dl class="grid gap-4 px-5 py-4 sm:grid-cols-2">
            @if ($execution->dispatch_group_id)
                <div class="rounded-lg bg-zinc-50 px-3 py-2">
                    <dt class="text-xs font-medium uppercase tracking-wider text-zinc-500">Dispatch group</dt>
                    <dd class="mt-1 space-y-1">
                        <a
                            href="{{ route('deck.activity.index', ['dispatch_group' => $execution->dispatch_group_id]) }}"
                            class="block break-all font-mono text-sm text-amber-600 hover:text-amber-500"
                        >
                            {{ $execution->dispatch_group_id }}
                        </a>
                        @if ($label = ExecutionObservability::groupSourceLabel($execution->dispatch_group_source))
                            <p class="text-xs text-zinc-600">{{ $label }}</p>
                        @endif
                    </dd>
                </div>
            @endif

            @if ($execution->batch_id)
                <div class="rounded-lg bg-zinc-50 px-3 py-2">
                    <dt class="text-xs font-medium uppercase tracking-wider text-zinc-500">Batch</dt>
                    <dd class="mt-1">
                        <a
                            href="{{ route('deck.activity.index', ['batch_id' => $execution->batch_id]) }}"
                            class="break-all font-mono text-sm text-amber-600 hover:text-amber-500"
                        >
                            {{ $execution->batch_id }}
                        </a>
                    </dd>
                </div>
            @endif

            @if ($parentExecution)
                <div class="rounded-lg bg-zinc-50 px-3 py-2">
                    <dt class="text-xs font-medium uppercase tracking-wider text-zinc-500">Dispatched by</dt>
                    <dd class="mt-1">
                        <a
                            href="{{ route('deck.activity.show', $parentExecution->activityRouteParameters()) }}"
                            class="font-mono text-sm font-medium text-amber-600 hover:text-amber-500"
                        >
                            {{ class_basename($parentExecution->job_class) }}
                        </a>
                        <p class="mt-1 font-mono text-[11px] text-zinc-500">{{ $parentExecution->uuid }}</p>
                    </dd>
                </div>
            @elseif ($execution->parent_job_uuid)
                <div class="rounded-lg bg-zinc-50 px-3 py-2">
                    <dt class="text-xs font-medium uppercase tracking-wider text-zinc-500">Dispatched by</dt>
                    <dd class="mt-1 space-y-1">
                        @if ($execution->parent_job_class)
                            <p class="font-mono text-sm text-zinc-900">{{ class_basename($execution->parent_job_class) }}</p>
                        @endif
                        <p class="break-all font-mono text-[11px] text-zinc-500">{{ $execution->parent_job_uuid }}</p>
                    </dd>
                </div>
            @endif
        </dl>

        @php($originEntries = ExecutionObservability::originEntries($execution->dispatch_origin))

        @if ($originEntries !== [])
            <div class="border-t border-zinc-100 px-5 py-4">
                <h3 class="text-xs font-medium uppercase tracking-wider text-zinc-500">Origin</h3>
                <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($originEntries as $entry)
                        <div class="rounded-lg bg-zinc-50 px-3 py-2">
                            <dt class="text-xs font-medium uppercase tracking-wider text-zinc-500">{{ $entry['label'] }}</dt>
                            <dd class="mt-1 break-all font-mono text-sm text-zinc-900">{{ $entry['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif
    </section>
@endif
