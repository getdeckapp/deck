<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Deck' }} — {{ config('app.name') }}</title>
    @include('deck::partials.favicon')
    @include('deck::partials.assets')
    @livewireStyles
</head>
<body class="h-full bg-zinc-100 antialiased">
@php
    $routeName = request()->route()?->getName() ?? '';
    $horizonUrl = \Deck\Deck\Support\DeckHorizon::dashboardUrl();
@endphp

<div class="flex min-h-full">
    {{-- v2 Dark sidebar --}}
    <aside class="relative hidden w-[280px] shrink-0 flex-col lg:flex">
        {{-- Deep zinc gradient base --}}
        <div class="absolute inset-0 bg-gradient-to-b from-zinc-950 via-[#0c0a18] to-zinc-950" aria-hidden="true"></div>
        {{-- Indigo aurora at top --}}
        <div
            class="absolute inset-x-0 top-0 h-[280px]"
            style="background: radial-gradient(60% 80% at 30% 0%, rgba(99,102,241,0.28) 0%, rgba(99,102,241,0.06) 40%, transparent 75%);"
            aria-hidden="true"
        ></div>
        {{-- Dot grid texture --}}
        <div
            class="absolute inset-0 opacity-[0.18]"
            style="background-image: radial-gradient(rgba(255,255,255,0.18) 1px, transparent 1px); background-size: 20px 20px; background-position: -10px -10px; -webkit-mask-image: linear-gradient(to bottom, transparent 0%, black 20%, black 100%); mask-image: linear-gradient(to bottom, transparent 0%, black 20%, black 100%);"
            aria-hidden="true"
        ></div>
        {{-- Right hairline --}}
        <div
            class="pointer-events-none absolute inset-y-0 right-0 w-px bg-gradient-to-b from-transparent via-white/[0.08] to-transparent"
            aria-hidden="true"></div>

        <div class="relative flex flex-1 flex-col">
            <div class="px-5 pt-6 pb-5">
                <a href="{{ route('deck.index') }}"
                   class="block rounded-xl focus:outline-2 focus:outline-offset-2 focus:outline-indigo-400">
                    <x-deck::logo variant="dark" />
                </a>
            </div>

            {{-- Search trigger --}}
            <div class="px-4 pb-4">
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('deck-search-open'))"
                    class="group flex w-full items-center gap-2.5 rounded-lg bg-white/[0.035] px-3 py-2 text-left text-[12.5px] text-zinc-400 ring-1 ring-inset ring-white/[0.06] transition hover:bg-white/[0.06] hover:text-zinc-200"
                >
                    <svg class="size-3.5 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75"
                         stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <span class="flex-1">Search jobs, UUIDs…</span>
                    <kbd
                        class="rounded-md bg-white/[0.06] px-1.5 py-0.5 font-mono text-[10px] text-zinc-400 ring-1 ring-white/10">⌘K</kbd>
                </button>
            </div>

            {{-- Project + environment chips --}}
            <div class="px-5 pb-5">
                <div class="flex flex-wrap items-center gap-2 text-[11.5px]">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/[0.04] px-2 py-0.5 font-mono text-zinc-300 ring-1 ring-white/[0.06]">
                        <span class="size-1.5 rounded-full bg-zinc-500" aria-hidden="true"></span>
                        {{ \Deck\Deck\Support\DeckInstallation::project() }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-indigo-500/[0.12] px-2 py-0.5 font-mono text-indigo-200 ring-1 ring-indigo-400/20">
                        <span class="size-1.5 rounded-full bg-indigo-400" aria-hidden="true"></span>
                        {{ \Deck\Deck\Support\DeckInstallation::environment() }}
                    </span>
                </div>
            </div>

            <nav class="flex-1 space-y-8 overflow-y-auto px-3 pb-6">
                <div>
                    <p class="mb-2.5 px-3 font-mono text-[10.5px] font-medium uppercase tracking-[0.16em] text-zinc-600">
                        Operations</p>
                    <div class="space-y-0.5">
                        <x-deck::nav-link :href="route('deck.index')" :active="$routeName === 'deck.index'">
                            <x-slot:icon>
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75"
                                     stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                </svg>
                            </x-slot:icon>
                            Overview
                        </x-deck::nav-link>

                        <x-deck::nav-link :href="route('deck.classes.index')"
                                          :active="str_contains($routeName, 'deck.classes')">
                            <x-slot:icon>
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75"
                                     stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v9a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 18V9a2.25 2.25 0 0 0-2.25-2.25m-12 0V9a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 18 9V6.878" />
                                </svg>
                            </x-slot:icon>
                            Jobs
                        </x-deck::nav-link>

                        <x-deck::nav-link :href="route('deck.activity.index')"
                                          :active="str_starts_with($routeName, 'deck.activity')">
                            <x-slot:icon>
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75"
                                     stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M3.75 12h16.5m-16.5 4.5h16.5M3.75 19.5h10.5" />
                                </svg>
                            </x-slot:icon>
                            Activity
                        </x-deck::nav-link>

                        <x-deck::nav-link :href="route('deck.workers.index')"
                                          :active="str_starts_with($routeName, 'deck.workers')">
                            <x-slot:icon>
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75"
                                     stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                </svg>
                            </x-slot:icon>
                            Workers
                        </x-deck::nav-link>
                    </div>
                </div>

                @if ($horizonUrl)
                    <div>
                        <p class="mb-2.5 px-3 font-mono text-[10.5px] font-medium uppercase tracking-[0.16em] text-zinc-600">
                            Horizon</p>
                        <a
                            href="{{ $horizonUrl }}"
                            class="group block rounded-lg bg-white/[0.035] px-3 py-3 ring-1 ring-inset ring-white/[0.06] transition hover:bg-white/[0.07] hover:ring-white/[0.10]"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[13px] font-medium text-zinc-100">Open Horizon</p>
                                    <p class="mt-0.5 text-[11.5px] leading-relaxed text-zinc-500">Workers, throughput,
                                        supervisors</p>
                                </div>
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-md bg-white/[0.04] text-zinc-400 ring-1 ring-inset ring-white/[0.06] transition group-hover:text-zinc-100">
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75"
                                             stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round"
                                                                         d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                    </span>
                            </div>
                        </a>
                    </div>
                @endif
            </nav>

            @if (config('deck.cloud.promo') && ! config('deck.cloud.enabled'))
                <div class="px-3 pb-4">
                    <div class="relative overflow-hidden rounded-xl p-4"
                         style="background: linear-gradient(135deg, rgba(99,102,241,0.18) 0%, rgba(139,92,246,0.12) 50%, rgba(99,102,241,0.06) 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,0.07), inset 0 0 0 1px rgba(129,140,248,0.18);">
                        <div class="pointer-events-none absolute right-2 top-2 opacity-[0.07]" aria-hidden="true">
                            <svg width="56" height="56" viewBox="0 0 40 40" fill="none">
                                <rect x="6" y="11" width="22" height="22" rx="4" fill="white" opacity="0.35" />
                                <rect x="9" y="9" width="22" height="22" rx="4" fill="white" opacity="0.6" />
                                <rect x="12" y="7" width="22" height="22" rx="4" fill="white" />
                            </svg>
                        </div>
                        <p class="font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-indigo-300">Deck
                            Cloud</p>
                        <p class="mt-1.5 text-[12.5px] font-medium leading-snug text-zinc-200">Multi-app visibility,
                            hosted &amp; zero infrastructure.</p>
                        <a
                            href="https://deckapp.cloud?utm_source=deck-oss&utm_medium=sidebar"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-3 inline-flex items-center gap-1 font-mono text-[11.5px] font-semibold text-indigo-300 transition hover:text-indigo-100"
                        >
                            Learn more
                            <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                    </div>
                </div>
            @endif

            <div class="border-t border-white/[0.04] px-5 py-4">
                <div class="flex items-center gap-2 text-[11px] text-zinc-600">
                    <span class="size-1.5 rounded-full bg-emerald-500" style="box-shadow: 0 0 6px rgba(16,185,129,0.6);"
                          aria-hidden="true"></span>
                    <span class="font-mono">Horizon flies the workers. Deck runs the operation.</span>
                </div>
            </div>
        </div>
    </aside>

    {{-- Main canvas --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 border-b border-zinc-200/70 bg-white/85 backdrop-blur-md lg:hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                <a href="{{ route('deck.index') }}"
                   class="rounded-lg focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600">
                    <x-deck::logo size="sm" :show-label="false" />
                </a>
                <nav class="flex items-center gap-0.5 text-[13px] font-medium overflow-x-auto">
                    <a href="{{ route('deck.index') }}" @class(['rounded-md px-2.5 py-1.5', 'bg-zinc-100 text-zinc-900' => $routeName === 'deck.index', 'text-zinc-500' => $routeName !== 'deck.index'])>Overview</a>
                    <a href="{{ route('deck.classes.index') }}" @class(['rounded-md px-2.5 py-1.5', 'bg-zinc-100 text-zinc-900' => str_contains($routeName, 'deck.classes'), 'text-zinc-500' => ! str_contains($routeName, 'deck.classes')])>Jobs</a>
                    <a href="{{ route('deck.activity.index') }}" @class(['rounded-md px-2.5 py-1.5', 'bg-zinc-100 text-zinc-900' => str_starts_with($routeName, 'deck.activity'), 'text-zinc-500' => ! str_starts_with($routeName, 'deck.activity')])>Activity</a>
                    <a href="{{ route('deck.workers.index') }}" @class(['rounded-md px-2.5 py-1.5', 'bg-zinc-100 text-zinc-900' => str_starts_with($routeName, 'deck.workers'), 'text-zinc-500' => ! str_starts_with($routeName, 'deck.workers')])>Workers</a>
                </nav>
            </div>
        </header>

        <main class="flex-1 bg-zinc-50 px-4 py-8 sm:px-6 lg:px-10">
            @if (session('status'))
                <x-deck::alert class="mb-6">{{ session('status') }}</x-deck::alert>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>

@livewire('deck.global-search')

@livewireScripts
<script>
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            window.dispatchEvent(new CustomEvent('deck-search-open'));
        }
    });
</script>
@stack('deck-scripts')
</body>
</html>
