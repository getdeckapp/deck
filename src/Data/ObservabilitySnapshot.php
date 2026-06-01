<?php

namespace Deck\Deck\Data;

use Deck\Deck\Enums\DispatchGroupSource;
use Illuminate\Support\Carbon;

readonly class ObservabilitySnapshot
{
    /**
     * @param  array<string, scalar|null>|null  $dispatchOrigin
     */
    public function __construct(
        public ?Carbon $dispatchedAt = null,
        public ?string $dispatchGroupId = null,
        public ?DispatchGroupSource $dispatchGroupSource = null,
        public ?string $batchId = null,
        public ?string $parentJobUuid = null,
        public ?string $parentJobClass = null,
        public ?array $dispatchOrigin = null,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function deckPayloadFragment(): ?array
    {
        if (! $this->hasPayloadData()) {
            return null;
        }

        $deck = [];

        if ($this->dispatchedAt !== null) {
            $deck['dispatched_at'] = $this->dispatchedAt->utc()->toIso8601String();
        }

        if ($this->dispatchGroupId !== null) {
            $deck['dispatch_group'] = array_filter([
                'id' => $this->dispatchGroupId,
                'source' => $this->dispatchGroupSource?->value,
            ]);
        }

        if ($this->batchId !== null) {
            $deck['batch_id'] = $this->batchId;
        }

        if ($this->parentJobUuid !== null) {
            $deck['parent_job'] = array_filter([
                'uuid' => $this->parentJobUuid,
                'class' => $this->parentJobClass,
            ]);
        }

        if ($this->dispatchOrigin !== null && $this->dispatchOrigin !== []) {
            $deck['dispatch_origin'] = $this->dispatchOrigin;
        }

        return $deck;
    }

    public function hasPayloadData(): bool
    {
        return $this->dispatchedAt !== null
            || $this->dispatchGroupId !== null
            || $this->batchId !== null
            || $this->parentJobUuid !== null
            || ($this->dispatchOrigin !== null && $this->dispatchOrigin !== []);
    }
}
