@props(['queues'])

@if ($queues->isNotEmpty())
    <section class="overflow-hidden rounded-2xl border border-amber-200/80 bg-amber-50/50 shadow-[0_1px_2px_rgba(0,0,0,0.04)] dark:border-amber-500/30 dark:bg-amber-500/5">
        <div class="border-b border-amber-200/60 px-5 py-4 dark:border-amber-500/20">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-amber-950 dark:text-amber-100">Queues without workers</h2>
                    <p class="mt-1 max-w-2xl text-xs leading-relaxed text-amber-800/90 dark:text-amber-200/80">
                        These queues have pending jobs but no Horizon worker processes are assigned to drain them.
                    </p>
                </div>
                <a href="{{ route('deck.workers.index') }}" class="shrink-0 text-sm font-medium text-amber-900 underline decoration-amber-400/60 underline-offset-2 hover:text-amber-950 dark:text-amber-200 dark:hover:text-white">
                    Workers & queues →
                </a>
            </div>
        </div>

        <ul class="divide-y divide-amber-200/60 dark:divide-amber-500/20">
            @foreach ($queues as $queue)
                <li class="px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-amber-950 dark:text-amber-50">
                                <span class="font-mono text-sm">{{ $queue->connection }}</span>
                                <span class="text-amber-700/70 dark:text-amber-300/60">/</span>
                                <span class="font-mono text-sm">{{ $queue->queue }}</span>
                            </p>
                            <p class="mt-1 text-sm text-amber-800/90 dark:text-amber-200/80">
                                {{ number_format($queue->pending) }} pending
                                @if ($queue->horizonStatus !== 'running')
                                    · Horizon {{ $queue->horizonStatus }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs leading-relaxed text-amber-800/80 dark:text-amber-200/70">
                        {{ $queue->suggestion }}
                        <a
                            href="https://laravel.com/docs/horizon#supervisors"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ml-1 font-medium text-amber-900 underline decoration-amber-400/50 underline-offset-2 hover:text-amber-950 dark:text-amber-200"
                        >Horizon supervisors docs</a>
                    </p>
                </li>
            @endforeach
        </ul>
    </section>
@endif
