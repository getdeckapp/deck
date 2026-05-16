@props([
    'enabled' => false,
    'seconds' => 4,
])

@php
    $interval = max(1, (int) $seconds).'s';
@endphp

<div
    {{ $attributes->class('space-y-8') }}
    @if ($enabled)
        wire:poll.{{ $interval }}
    @endif
>
    {{ $slot }}
</div>
