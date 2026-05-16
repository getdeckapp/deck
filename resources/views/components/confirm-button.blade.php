@props([
    'action',
    'params' => [],
    'title',
    'message',
    'confirmLabel' => 'Confirm',
    'progressLabel' => 'Working…',
    'tone' => 'primary',
])

<button
    type="button"
    {{ $attributes }}
    wire:click.stop="requestConfirmation(@js($action), @js($params), @js($title), @js($message), @js($confirmLabel), @js($progressLabel), @js($tone))"
>
    {{ $slot }}
</button>
