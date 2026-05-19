<?php

namespace Deck\Deck\Cloud;

class SyncThrottle
{
    /**
     * @var array<string, int>
     */
    private array $lastSyncedAt = [];

    public function shouldSync(string $channel, string $key): bool
    {
        $cacheKey = $channel.':'.$key;
        $now = time();

        if (isset($this->lastSyncedAt[$cacheKey]) && ($now - $this->lastSyncedAt[$cacheKey]) < DeckCloud::syncIntervalSeconds()) {
            return false;
        }

        $this->lastSyncedAt[$cacheKey] = $now;

        return true;
    }

    public function reset(): void
    {
        $this->lastSyncedAt = [];
    }
}
