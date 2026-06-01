<?php

namespace Deck\Deck\Cloud\Events;

use Deck\Deck\Cloud\Connection\HttpClient;
use Deck\Deck\Cloud\DeckCloud;

class CloudEventBuffer
{
    /** @var list<array<string, mixed>> */
    private array $events = [];

    private bool $flushOnTerminateRegistered = false;

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function push(array $event): void
    {
        $this->events[] = $event;

        if (count($this->events) >= $this->batchSize()) {
            $this->flush();

            return;
        }

        $this->registerFlushOnTerminate();
    }

    public function flush(): void
    {
        if ($this->events === []) {
            return;
        }

        $events = $this->events;
        $this->events = [];

        $this->http->post(DeckCloud::EventsIngestPath, [
            'events' => $events,
        ]);
    }

    private function batchSize(): int
    {
        return max(1, min(100, (int) config('deck.cloud.events.batch_size', 25)));
    }

    private function registerFlushOnTerminate(): void
    {
        if ($this->flushOnTerminateRegistered) {
            return;
        }

        $this->flushOnTerminateRegistered = true;

        app()->terminating(function (): void {
            app(self::class)->flush();
        });
    }
}
