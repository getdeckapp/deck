<?php

namespace Deck\Deck\Cloud;

class WorkerReporter
{
    private const int ChunkSize = 100;

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * @param  list<WorkerSnapshot>  $snapshots
     */
    public function send(array $snapshots): void
    {
        if ($snapshots === []) {
            return;
        }

        $workers = array_map(
            fn (WorkerSnapshot $snapshot): array => $snapshot->toArray(),
            $snapshots,
        );

        foreach (array_chunk($workers, self::ChunkSize) as $chunk) {
            $this->http->post('/api/v1/ingest/workers', [
                'workers' => $chunk,
            ]);
        }
    }
}
