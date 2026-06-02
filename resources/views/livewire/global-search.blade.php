<div
    x-data="{ open: false }"
    x-on:deck-search-open.window="open = true; $wire.resetQuery(); $nextTick(() => $refs.deckSearch?.focus())"
    x-on:keydown.escape.window="open = false"
>
    {{-- Modal overlay --}}
    <template x-if="open">
        <div class="fixed inset-0 z-[300]" role="dialog" aria-modal="true" aria-label="Search">

            {{-- Backdrop --}}
            <div
                class="fixed inset-0 bg-zinc-900/50 backdrop-blur-[3px]"
                @click="open = false"
                aria-hidden="true"
            ></div>

            {{-- Panel --}}
            <div class="pointer-events-none fixed inset-0 flex items-start justify-center px-4 pt-[12vh]">
                <div class="pointer-events-auto w-full max-w-xl overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-[0_8px_40px_-8px_rgba(15,23,42,0.24),0_32px_64px_-16px_rgba(15,23,42,0.20)]">

                    {{-- Input row — wire:ignore keeps Livewire from morphing it --}}
                    <div class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3.5" wire:ignore>
                        <span class="shrink-0">
                            <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </span>
                        <input
                            x-ref="deckSearch"
                            type="search"
                            placeholder="Search jobs, UUIDs…"
                            autocomplete="off"
                            @input.debounce.250ms="$wire.set('query', $event.target.value)"
                            class="flex-1 bg-transparent text-[15px] text-zinc-900 placeholder-zinc-400 focus:outline-none"
                        >
                        <kbd class="hidden rounded-md border border-zinc-200 bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-500 sm:inline">Esc</kbd>
                    </div>

                    {{-- Results — Livewire re-renders only this part --}}
                    <div class="max-h-[480px] overflow-y-auto">
                        @php
                            $results = $this->results;
                            $hasClasses = $results['classes']->isNotEmpty();
                            $hasExecutions = $results['executions']->isNotEmpty();
                            $hasAny = $hasClasses || $hasExecutions;
                        @endphp

                        @if (strlen($query) < 2)
                            <div class="px-4 py-10 text-center">
                                <p class="text-[13px] text-zinc-400">Type at least 2 characters to search job classes and executions.</p>
                            </div>
                        @elseif (! $hasAny)
                            <div class="px-4 py-10 text-center">
                                <p class="text-[13px] font-medium text-zinc-900">No results for "{{ $query }}"</p>
                                <p class="mt-1 text-[13px] text-zinc-500">Try a job class name or UUID fragment.</p>
                            </div>
                        @else
                            @if ($hasClasses)
                                <div class="px-3 pb-1 pt-3">
                                    <p class="px-2 font-mono text-[10px] font-semibold uppercase tracking-[0.14em] text-zinc-400">Job Classes</p>
                                </div>
                                <ul role="list">
                                    @foreach ($results['classes'] as $stat)
                                        <li>
                                            <a
                                                href="{{ route('deck.classes.show', ['jobClass' => $stat->job_class]) }}"
                                                @click="open = false"
                                                class="group flex items-center gap-3 px-3 py-2.5 hover:bg-amber-50/60"
                                            >
                                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-500 ring-1 ring-amber-100">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v9a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 18V9a2.25 2.25 0 0 0-2.25-2.25m-12 0V9a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 18 9V6.878" /></svg>
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-[13.5px] font-semibold text-zinc-900 group-hover:text-amber-700">{{ class_basename($stat->job_class) }}</p>
                                                    <p class="truncate font-mono text-[11px] text-zinc-400">{{ $stat->job_class }}</p>
                                                </div>
                                                @if ($stat->last_status)
                                                    <x-deck::badge :status="$stat->last_status->value">{{ $stat->last_status->value }}</x-deck::badge>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($hasExecutions)
                                <div class="px-3 pb-1 pt-3">
                                    <p class="px-2 font-mono text-[10px] font-semibold uppercase tracking-[0.14em] text-zinc-400">Recent Executions</p>
                                </div>
                                <ul role="list">
                                    @foreach ($results['executions'] as $execution)
                                        <li>
                                            <a
                                                href="{{ route('deck.activity.show', $execution->activityRouteParameters()) }}"
                                                @click="open = false"
                                                class="group flex items-center gap-3 px-3 py-2.5 hover:bg-amber-50/60"
                                            >
                                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-50 text-zinc-400 ring-1 ring-zinc-100">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 4.5h16.5M3.75 19.5h10.5" /></svg>
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-[13.5px] font-semibold text-zinc-900 group-hover:text-amber-700">{{ class_basename($execution->job_class) }}</p>
                                                    <p class="font-mono text-[11px] text-zinc-400">{{ $execution->uuid }}</p>
                                                </div>
                                                <div class="flex shrink-0 flex-col items-end gap-1">
                                                    <x-deck::badge :status="$execution->status->value">{{ $execution->status->value }}</x-deck::badge>
                                                    <span class="font-mono text-[10.5px] text-zinc-400">{{ $execution->started_at->diffForHumans() }}</span>
                                                </div>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between border-t border-zinc-100 px-4 py-2.5">
                        <p class="font-mono text-[11px] text-zinc-400">
                            @if ($hasAny ?? false)
                                {{ $results['classes']->count() + $results['executions']->count() }} results
                            @endif
                        </p>
                        <div class="flex items-center gap-3 font-mono text-[11px] text-zinc-400">
                            <span><kbd class="rounded border border-zinc-200 bg-zinc-50 px-1 py-0.5 text-[10px]">↵</kbd> open</span>
                            <span><kbd class="rounded border border-zinc-200 bg-zinc-50 px-1 py-0.5 text-[10px]">Esc</kbd> close</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
