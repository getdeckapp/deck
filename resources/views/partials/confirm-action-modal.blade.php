@if ($pendingConfirmation)
    @php
        $hasChoices = ! empty($pendingConfirmation['choices'] ?? []);
        $tone = $pendingConfirmation['tone'] ?? ($hasChoices ? 'warning' : 'primary');
        $iconWrap = match ($tone) {
            'danger' => 'bg-red-100 text-red-600',
            'warning' => 'bg-amber-100 text-amber-700',
            default => 'bg-indigo-100 text-indigo-600',
        };
        $confirmButton = match ($tone) {
            'danger' => 'bg-red-600 text-white hover:bg-red-500 focus-visible:outline-red-600',
            'warning' => 'bg-amber-600 text-white hover:bg-amber-500 focus-visible:outline-amber-600',
            default => 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600',
        };
        $choiceButton = fn (string $choiceTone) => match ($choiceTone) {
            'danger' => 'bg-red-600 text-white hover:bg-red-500 focus-visible:outline-red-600',
            'warning' => 'bg-amber-600 text-white hover:bg-amber-500 focus-visible:outline-amber-600',
            default => 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600',
        };
        $confirmKey = $hasChoices
            ? 'choices-'.md5(json_encode($pendingConfirmation['choices']))
            : ($pendingConfirmation['method'] ?? 'action').'-'.md5(json_encode($pendingConfirmation['arguments'] ?? []));
    @endphp

    @teleport('body')
        <div
            class="fixed inset-0 z-[200]"
            role="dialog"
            aria-modal="true"
            aria-labelledby="deck-confirm-title"
            aria-describedby="deck-confirm-message"
            wire:key="deck-confirm-{{ $confirmKey }}"
        >
            <div
                class="fixed inset-0 bg-zinc-900/60 backdrop-blur-[2px]"
                wire:click="cancelConfirmation"
                wire:loading.class="pointer-events-none"
                wire:target="executeConfirmedAction"
                aria-hidden="true"
            ></div>

            <div class="fixed inset-0 flex items-end justify-center p-4 sm:items-center sm:p-6 pointer-events-none">
                <div @class([
                    'pointer-events-auto w-full overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-2xl ring-1 ring-black/10',
                    'max-w-lg' => $hasChoices,
                    'max-w-md' => ! $hasChoices,
                ])>
                    <div class="p-6">
                        <div class="flex gap-4">
                            <div @class(['flex size-11 shrink-0 items-center justify-center rounded-xl', $iconWrap])>
                                @if ($tone === 'danger')
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                @elseif ($tone === 'warning')
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                @else
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                    </svg>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1 pt-0.5">
                                <h3 id="deck-confirm-title" class="text-base font-semibold text-zinc-900">
                                    {{ $pendingConfirmation['title'] }}
                                </h3>
                                <p id="deck-confirm-message" class="mt-2 text-sm leading-relaxed text-zinc-600">
                                    {{ $pendingConfirmation['message'] }}
                                </p>
                            </div>
                        </div>

                        @if ($hasChoices)
                            <ul class="mt-5 space-y-3" role="list">
                                @foreach ($pendingConfirmation['choices'] as $choice)
                                    <li class="rounded-xl border border-zinc-200/80 bg-zinc-50/50 p-4">
                                        <p class="text-sm leading-relaxed text-zinc-600">{{ $choice['description'] }}</p>
                                        <button
                                            type="button"
                                            @class([
                                                'mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-70 sm:w-auto',
                                                $choiceButton($choice['tone']),
                                            ])
                                            wire:click="executeConfirmedAction(@js($choice['method']))"
                                            wire:loading.attr="disabled"
                                            wire:target="executeConfirmedAction"
                                        >
                                            <span wire:loading.remove wire:target="executeConfirmedAction">
                                                {{ $choice['label'] }}
                                            </span>
                                            <span wire:loading wire:target="executeConfirmedAction" class="inline-flex items-center gap-2">
                                                <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                {{ $choice['progressLabel'] }}
                                            </span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="mt-6 flex justify-end">
                                <button
                                    type="button"
                                    class="inline-flex justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-zinc-900 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50 disabled:opacity-50"
                                    wire:click="cancelConfirmation"
                                    wire:loading.attr="disabled"
                                    wire:target="executeConfirmedAction"
                                >
                                    Close
                                </button>
                            </div>
                        @else
                            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                <button
                                    type="button"
                                    class="inline-flex justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-zinc-900 shadow-xs ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50 disabled:opacity-50"
                                    wire:click="cancelConfirmation"
                                    wire:loading.attr="disabled"
                                    wire:target="executeConfirmedAction"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    @class([
                                        'inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-70',
                                        $confirmButton,
                                    ])
                                    wire:click="executeConfirmedAction"
                                    wire:loading.attr="disabled"
                                >
                                    <span wire:loading.remove wire:target="executeConfirmedAction">
                                        {{ $pendingConfirmation['confirmLabel'] }}
                                    </span>
                                    <span wire:loading wire:target="executeConfirmedAction" class="inline-flex items-center gap-2">
                                        <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ $pendingConfirmation['progressLabel'] }}
                                    </span>
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endteleport
@endif
