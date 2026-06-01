@props([
    'connection',
    'variant' => 'sidebar',
])

@php
    use Deck\Deck\Cloud\Connection\CloudConnectionState;

    $dotClass = match ($connection->state) {
        CloudConnectionState::Connected => 'bg-emerald-400',
        CloudConnectionState::Misconfigured => 'bg-amber-400',
        CloudConnectionState::Unauthorized, CloudConnectionState::Unreachable => 'bg-rose-400',
        default => 'bg-zinc-500',
    };

    $dotGlow = match ($connection->state) {
        CloudConnectionState::Connected => '0 0 6px rgba(52,211,153,0.65)',
        CloudConnectionState::Misconfigured => '0 0 6px rgba(251,191,36,0.55)',
        CloudConnectionState::Unauthorized, CloudConnectionState::Unreachable => '0 0 6px rgba(251,113,133,0.55)',
        default => 'none',
    };

    $labelClass = match ($connection->state) {
        CloudConnectionState::Connected => 'text-emerald-300',
        CloudConnectionState::Misconfigured => 'text-amber-300',
        CloudConnectionState::Unauthorized, CloudConnectionState::Unreachable => 'text-rose-300',
        default => 'text-zinc-400',
    };
@endphp

@if ($variant === 'sidebar')
    <div class="px-3 pb-4">
        <div class="rounded-xl bg-white/[0.035] p-4 ring-1 ring-inset ring-white/[0.06]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-indigo-300">Deck Cloud</p>
                    <div class="mt-2 flex items-center gap-2">
                        <span
                            class="size-2 shrink-0 rounded-full {{ $dotClass }}"
                            style="box-shadow: {{ $dotGlow }};"
                            aria-hidden="true"
                        ></span>
                        <span class="text-[13px] font-semibold {{ $labelClass }}">{{ $connection->label }}</span>
                    </div>
                    <p class="mt-1.5 font-mono text-[11px] text-zinc-400">
                        {{ $connection->host }}
                        <span class="text-zinc-600">·</span>
                        {{ $connection->project }}/{{ $connection->environment }}
                    </p>
                    <p class="mt-1.5 text-[11.5px] leading-relaxed text-zinc-500">{{ $connection->detail }}</p>
                    @if ($connection->workersEnabled || $connection->commandsEnabled)
                        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.12em] text-zinc-600">
                            @if ($connection->workersEnabled)
                                <span>Workers</span>
                            @endif
                            @if ($connection->workersEnabled && $connection->commandsEnabled)
                                <span class="text-zinc-700"> · </span>
                            @endif
                            @if ($connection->commandsEnabled)
                                <span>Commands</span>
                            @endif
                        </p>
                    @endif
                </div>
            </div>
            @if ($connection->dashboardUrl)
                <a
                    href="{{ $connection->dashboardUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-flex items-center gap-1 font-mono text-[11.5px] font-semibold text-indigo-300 transition hover:text-indigo-100"
                >
                    Open Cloud
                    <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </a>
            @endif
        </div>
    </div>
@else
    @php
        $badgeClass = match ($connection->state) {
            CloudConnectionState::Connected => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
            CloudConnectionState::Misconfigured => 'bg-amber-50 text-amber-900 ring-amber-600/20',
            default => 'bg-rose-50 text-rose-800 ring-rose-600/20',
        };
        $badgeDot = match ($connection->state) {
            CloudConnectionState::Connected => 'bg-emerald-500',
            CloudConnectionState::Misconfigured => 'bg-amber-500',
            default => 'bg-rose-500',
        };
    @endphp
    <div class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[12.5px] font-semibold ring-1 ring-inset {{ $badgeClass }}" title="{{ $connection->detail }}">
        <span class="relative inline-flex size-2">
            @if ($connection->isHealthy())
                <span class="absolute inset-0 animate-ping rounded-full bg-emerald-400 opacity-60" aria-hidden="true"></span>
            @endif
            <span class="relative size-2 rounded-full {{ $badgeDot }}" aria-hidden="true"></span>
        </span>
        Cloud · {{ $connection->label }}
    </div>
@endif
