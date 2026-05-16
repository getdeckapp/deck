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
        $horizonUrl = \TorMorten\Deck\Support\DeckHorizon::dashboardUrl();
    @endphp

    <div class="flex min-h-full">
        {{-- Premium dark sidebar --}}
        <aside class="relative hidden w-72 shrink-0 flex-col lg:flex">
            <div class="absolute inset-0 bg-gradient-to-b from-zinc-950 via-zinc-950 to-zinc-900" aria-hidden="true"></div>
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-indigo-900/20 via-transparent to-transparent" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 w-px bg-gradient-to-b from-transparent via-white/10 to-transparent" aria-hidden="true"></div>

            <div class="relative flex flex-1 flex-col">
                <div class="border-b border-white/[0.06] px-6 py-7">
                    <a href="{{ route('deck.index') }}" class="rounded-lg focus:outline-2 focus:outline-offset-2 focus:outline-indigo-400">
                        <x-deck::logo variant="dark" />
                    </a>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-full bg-white/[0.06] px-2.5 py-1 text-xs font-medium text-zinc-300 ring-1 ring-white/10">
                            {{ \TorMorten\Deck\Support\DeckInstallation::project() }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-indigo-500/15 px-2.5 py-1 text-xs font-medium text-indigo-200 ring-1 ring-indigo-400/25">
                            {{ \TorMorten\Deck\Support\DeckInstallation::environment() }}
                        </span>
                    </div>
                </div>

                <nav class="flex-1 space-y-8 overflow-y-auto px-4 py-6">
                    <div>
                        <p class="mb-3 px-3.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-500">Operations</p>
                        <div class="space-y-1">
                            <x-deck::nav-link :href="route('deck.index')" :active="$routeName === 'deck.index'">
                                <x-slot:icon>
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>
                                </x-slot:icon>
                                Overview
                            </x-deck::nav-link>

                            <x-deck::nav-link :href="route('deck.classes.index')" :active="str_contains($routeName, 'deck.classes')">
                                <x-slot:icon>
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v9a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 18V9a2.25 2.25 0 0 0-2.25-2.25m-12 0V9a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 18 9V6.878" /></svg>
                                </x-slot:icon>
                                Jobs
                            </x-deck::nav-link>

                            <x-deck::nav-link :href="route('deck.activity.index')" :active="str_starts_with($routeName, 'deck.activity')">
                                <x-slot:icon>
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 4.5h16.5M3.75 19.5h10.5" /></svg>
                                </x-slot:icon>
                                Activity
                            </x-deck::nav-link>

                            <x-deck::nav-link :href="route('deck.workers.index')" :active="str_starts_with($routeName, 'deck.workers')">
                                <x-slot:icon>
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                </x-slot:icon>
                                Workers
                            </x-deck::nav-link>
                        </div>
                    </div>

                    @if ($horizonUrl)
                        <div class="px-1">
                            <p class="mb-3 px-2.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-500">Horizon</p>
                            <a
                                href="{{ $horizonUrl }}"
                                class="group block rounded-xl bg-white/[0.04] p-4 ring-1 ring-white/10 transition hover:bg-white/[0.07] hover:ring-white/20"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-white">Open Horizon</p>
                                        <p class="mt-1 text-xs leading-relaxed text-zinc-500">Workers, throughput, supervisors</p>
                                    </div>
                                    <span class="flex size-8 items-center justify-center rounded-lg bg-white/5 text-zinc-400 ring-1 ring-white/10 transition group-hover:text-white">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                    </span>
                                </div>
                            </a>
                        </div>
                    @endif
                </nav>

                <div class="border-t border-white/[0.06] px-6 py-4">
                    <p class="text-xs leading-relaxed text-zinc-600">
                        <span class="text-zinc-500">Horizon flies the workers.</span>
                        <span class="text-zinc-400"> Deck runs the operation.</span>
                    </p>
                </div>
            </div>
        </aside>

        {{-- Main canvas --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="border-b border-zinc-200/80 bg-white/90 backdrop-blur-md lg:hidden">
                <div class="flex items-center justify-between gap-4 px-4 py-3">
                    <a href="{{ route('deck.index') }}" class="rounded-lg focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600">
                        <x-deck::logo size="sm" :show-label="false" />
                    </a>
                    <nav class="flex items-center gap-1 text-sm font-medium">
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

    @livewireScripts
    @stack('deck-scripts')
</body>
</html>
