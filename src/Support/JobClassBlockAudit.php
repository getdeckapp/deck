<?php

namespace TorMorten\Deck\Support;

use Illuminate\Support\Carbon;

readonly class JobClassBlockAudit
{
    public function __construct(
        public ?string $reason,
        public Carbon $blockedAt,
        public ?string $blockedBy,
    ) {}

    /**
     * @param  array{reason?: string|null, blocked_at?: string, blocked_by?: string|null}|null  $payload
     */
    public static function fromCache(?array $payload): ?self
    {
        if ($payload === null || ! isset($payload['blocked_at'])) {
            return null;
        }

        return new self(
            reason: isset($payload['reason']) && $payload['reason'] !== ''
                ? (string) $payload['reason']
                : null,
            blockedAt: Carbon::parse($payload['blocked_at']),
            blockedBy: isset($payload['blocked_by']) && $payload['blocked_by'] !== ''
                ? (string) $payload['blocked_by']
                : null,
        );
    }
}
