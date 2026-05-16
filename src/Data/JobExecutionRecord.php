<?php

namespace TorMorten\Deck\Data;

use Illuminate\Support\Carbon;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Support\QueuedJobMetadata;

readonly class JobExecutionRecord
{
    /**
     * @param  list<string>|null  $tags
     */
    public function __construct(
        public QueuedJobMetadata $metadata,
        public string $project,
        public string $environment,
        public JobExecutionStatus $status,
        public Carbon $startedAt,
        public ?Carbon $finishedAt = null,
        public ?int $durationMs = null,
        public ?string $exceptionClass = null,
        public ?string $exceptionMessage = null,
        public ?string $exceptionTrace = null,
        public ?array $tags = null,
        public ?array $context = null,
    ) {}
}
