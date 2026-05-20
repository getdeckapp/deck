<?php

namespace Deck\Deck\Cloud;

class WorkerReporter
{
    private const int ChunkSize = 100;

    private static bool $lastSendAttempted = false;

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public static function lastSendAttempted(): bool
    {
        return self::$lastSendAttempted;
    }

    /**
     * @param  list<WorkerSnapshot>  $snapshots
     * @param  list<QueueWorkloadSnapshot>  $queueSnapshots
     */
    public function send(array $snapshots, array $queueSnapshots = []): bool
    {
        self::$lastSendAttempted = false;

        if (! DeckCloud::workersEnabled()) {
            return false;
        }

        if ($snapshots === [] && $queueSnapshots === []) {
            return false;
        }

        $workers = array_map(
            fn (WorkerSnapshot $snapshot): array => $snapshot->toArray(),
            $snapshots,
        );

        $queues = array_map(
            fn (QueueWorkloadSnapshot $snapshot): array => $snapshot->toArray(),
            $queueSnapshots,
        );

        if ($workers === []) {
            self::$lastSendAttempted = true;

            return $this->http->post('/api/v1/ingest/workers', [
                'queues' => $queues,
            ]);
        }

        $chunks = array_chunk($workers, self::ChunkSize);
        $accepted = false;

        foreach ($chunks as $index => $chunk) {
            $payload = ['workers' => $chunk];

            if ($index === 0 && $queues !== []) {
                $payload['queues'] = $queues;
            }

            self::$lastSendAttempted = true;

            $accepted = $this->http->post('/api/v1/ingest/workers', $payload) || $accepted;
        }

        return $accepted;
    }
}
