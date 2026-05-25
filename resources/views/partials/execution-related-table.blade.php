@props(['executions', 'title', 'description'])

@if ($executions->isNotEmpty())
    <section class="overflow-hidden rounded-2xl border border-zinc-200/60 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_rgba(0,0,0,0.06)]">
        <div class="border-b border-zinc-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-zinc-900">{{ $title }}</h2>
            <p class="mt-1 text-xs text-zinc-500">{{ $description }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/60">
                        <th class="px-4 py-3 font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Job</th>
                        <th class="px-4 py-3 font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Status</th>
                        <th class="px-4 py-3 font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Wait</th>
                        <th class="px-4 py-3 font-mono text-[10.5px] font-semibold uppercase tracking-[0.10em] text-zinc-500">Started</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($executions as $related)
                        <tr class="hover:bg-zinc-50/60">
                            <td class="px-4 py-3">
                                <a
                                    href="{{ route('deck.activity.show', $related->activityRouteParameters()) }}"
                                    class="font-mono font-medium text-zinc-900 hover:text-indigo-600"
                                >
                                    {{ class_basename($related->job_class) }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <x-deck::badge :status="$related->status->value">{{ $related->status->value }}</x-deck::badge>
                            </td>
                            <td class="px-4 py-3 font-mono text-zinc-500">{{ $related->formattedWait() }}</td>
                            <td class="px-4 py-3 text-zinc-500">{{ $related->started_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
