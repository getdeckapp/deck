@props(['execution'])

@if ($execution->status->value === 'running')
    <button
        type="button"
        {{ $attributes->merge(['class' => 'relative z-10 text-sm font-semibold text-red-600 hover:text-red-500']) }}
        wire:click.stop="requestCancelExecutionConfirmation(@js($execution->uuid), @js($execution->attempt), @js($execution->isCancellationPending()))"
    >
        Cancel
    </button>
@endif
