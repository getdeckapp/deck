<?php

namespace Deck\Deck\Support;

use Illuminate\Support\Facades\Log;

class DeckRecordingDebug
{
    public static function trace(string $phase, QueuedJobMetadata $metadata): void
    {
        if (! config('deck.debug_recording', false)) {
            return;
        }

        Log::info('Deck recording trace.', [
            'phase' => $phase,
            'uuid' => $metadata->uuid,
            'job_class' => $metadata->jobClass,
            'connection' => $metadata->connection,
            'queue' => $metadata->queue,
            'attempt' => $metadata->attempt,
            'project' => DeckInstallation::project(),
            'environment' => DeckInstallation::environment(),
        ]);
    }
}
