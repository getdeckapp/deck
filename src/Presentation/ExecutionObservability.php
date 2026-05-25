<?php

namespace Deck\Deck\Presentation;

use Deck\Deck\Enums\DispatchGroupSource;
use Deck\Deck\Models\JobExecution;
use Illuminate\Database\Eloquent\Builder;

class ExecutionObservability
{
    public static function hasObservability(JobExecution $execution): bool
    {
        return $execution->dispatched_at !== null
            || $execution->wait_ms !== null
            || $execution->dispatch_group_id !== null
            || $execution->batch_id !== null
            || $execution->parent_job_uuid !== null
            || ($execution->dispatch_origin !== null && $execution->dispatch_origin !== []);
    }

    public static function groupSourceLabel(?DispatchGroupSource $source): ?string
    {
        return match ($source) {
            DispatchGroupSource::Request => 'HTTP request',
            DispatchGroupSource::Lineage => 'Parent job',
            DispatchGroupSource::Manual => 'Manual',
            DispatchGroupSource::Batch => 'Batch',
            default => null,
        };
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public static function originEntries(?array $origin): array
    {
        if ($origin === null || $origin === []) {
            return [];
        }

        $labels = [
            'type' => 'Type',
            'method' => 'Method',
            'route' => 'Route',
            'uri' => 'URI',
            'request_id' => 'Request ID',
            'command' => 'Command',
            'schedule' => 'Schedule',
            'parent_uuid' => 'Parent UUID',
            'parent_class' => 'Parent class',
            'label' => 'Label',
            'source' => 'Source',
        ];

        $entries = [];

        foreach ($labels as $key => $label) {
            if (! array_key_exists($key, $origin) || $origin[$key] === null || $origin[$key] === '') {
                continue;
            }

            $entries[] = [
                'label' => $label,
                'value' => (string) $origin[$key],
            ];
        }

        return $entries;
    }

    /**
     * @return Builder<JobExecution>
     */
    public static function relatedGroupQuery(JobExecution $execution): Builder
    {
        return JobExecution::query()
            ->forInstallation()
            ->where('dispatch_group_id', $execution->dispatch_group_id)
            ->where(function (Builder $query) use ($execution): void {
                $query->where('uuid', '!=', $execution->uuid)
                    ->orWhere('attempt', '!=', $execution->attempt);
            })
            ->latest('started_at')
            ->limit(10);
    }

    /**
     * @return Builder<JobExecution>
     */
    public static function childExecutionsQuery(JobExecution $execution): Builder
    {
        return JobExecution::query()
            ->forInstallation()
            ->where('parent_job_uuid', $execution->uuid)
            ->latest('started_at')
            ->limit(10);
    }

    public static function parentExecution(JobExecution $execution): ?JobExecution
    {
        if ($execution->parent_job_uuid === null) {
            return null;
        }

        return JobExecution::query()
            ->forInstallation()
            ->where('uuid', $execution->parent_job_uuid)
            ->latest('started_at')
            ->first();
    }
}
