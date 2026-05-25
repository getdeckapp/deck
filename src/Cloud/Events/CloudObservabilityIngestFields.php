<?php

namespace Deck\Deck\Cloud\Events;

use Deck\Deck\Data\ObservabilitySnapshot;
use Deck\Deck\Models\JobExecution;

class CloudObservabilityIngestFields
{
    /**
     * @return array<string, mixed>
     */
    public static function fromSnapshot(?ObservabilitySnapshot $observability, ?int $waitMs = null): array
    {
        if ($observability === null && $waitMs === null) {
            return [];
        }

        $fields = [];

        if ($observability?->dispatchedAt !== null) {
            $fields['dispatched_at'] = $observability->dispatchedAt->utc()->toIso8601String();
        }

        if ($waitMs !== null) {
            $fields['wait_ms'] = max(0, $waitMs);
        }

        if ($observability?->dispatchGroupId !== null) {
            $fields['dispatch_group_id'] = $observability->dispatchGroupId;
        }

        if ($observability?->dispatchGroupSource !== null) {
            $fields['dispatch_group_source'] = $observability->dispatchGroupSource->value;
        }

        if ($observability?->batchId !== null) {
            $fields['batch_id'] = $observability->batchId;
        }

        if ($observability?->parentJobUuid !== null) {
            $fields['parent_job_uuid'] = $observability->parentJobUuid;
        }

        if ($observability?->parentJobClass !== null) {
            $fields['parent_job_class'] = $observability->parentJobClass;
        }

        if ($observability?->dispatchOrigin !== null && $observability->dispatchOrigin !== []) {
            $fields['dispatch_origin'] = $observability->dispatchOrigin;
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromExecution(JobExecution $execution): array
    {
        $observability = new ObservabilitySnapshot(
            dispatchedAt: $execution->dispatched_at,
            dispatchGroupId: $execution->dispatch_group_id,
            dispatchGroupSource: $execution->dispatch_group_source,
            batchId: $execution->batch_id,
            parentJobUuid: $execution->parent_job_uuid,
            parentJobClass: $execution->parent_job_class,
            dispatchOrigin: $execution->dispatch_origin,
        );

        return self::fromSnapshot($observability, $execution->wait_ms);
    }
}
