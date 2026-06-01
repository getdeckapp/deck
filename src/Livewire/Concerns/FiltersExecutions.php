<?php

namespace Deck\Deck\Livewire\Concerns;

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property string $search
 * @property string $status
 * @property string $queue
 * @property string $connection
 * @property string $tag
 * @property string $dispatch_group
 * @property string $batch_id
 */
trait FiltersExecutions
{
    protected function applyExecutionFilters(Builder $query): Builder
    {
        if ($this->search !== '') {
            $search = $this->search;

            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('job_class', 'like', '%'.$search.'%')
                    ->orWhere('uuid', 'like', '%'.$search.'%')
                    ->orWhere('dispatch_group_id', 'like', '%'.$search.'%')
                    ->orWhere('parent_job_uuid', 'like', '%'.$search.'%')
                    ->orWhere('parent_job_class', 'like', '%'.$search.'%')
                    ->orWhere('exception_message', 'like', '%'.$search.'%')
                    ->orWhere('exception_class', 'like', '%'.$search.'%');
            });
        }

        if ($this->status !== '' && JobExecutionStatus::tryFrom($this->status)) {
            $query->where('status', $this->status);
        }

        if ($this->queue !== '') {
            $query->where('queue', $this->queue);
        }

        if ($this->connection !== '') {
            $query->where('connection', $this->connection);
        }

        if ($this->tag !== '') {
            $query->whereJsonContains('tags', $this->tag);
        }

        if ($this->dispatch_group !== '') {
            $query->where('dispatch_group_id', $this->dispatch_group);
        }

        if ($this->batch_id !== '') {
            $query->where('batch_id', $this->batch_id);
        }

        return $query;
    }

    /**
     * @return Builder<JobExecution>
     */
    protected function filteredExecutionsQuery(): Builder
    {
        return $this->applyExecutionFilters(
            JobExecution::query()->forInstallation(),
        );
    }
}
