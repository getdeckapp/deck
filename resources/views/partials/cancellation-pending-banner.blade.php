<div class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-5 py-4 shadow-sm">
    <div class="flex gap-3">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
            <svg class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-amber-900">Cancellation in progress</p>
            <p class="mt-1 text-sm leading-relaxed text-amber-800/90">
                The cancel flag is set. The worker will stop at the next cooperative check if this job uses the
                <span class="font-mono text-xs">Cancellable</span> middleware. Open
                <strong class="font-semibold">Cancel</strong> and choose <strong class="font-semibold">Force cancel</strong> to remove a reserved Redis payload and mark this execution cancelled immediately.
            </p>
        </div>
    </div>
</div>
