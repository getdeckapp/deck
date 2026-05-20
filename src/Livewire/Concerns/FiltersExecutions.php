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
 */
trait FiltersExecutions
{
    protected function applyExecutionFilters(Builder $query): Builder
    {
        if ($this->search !== '') {
            $search = $this->search;

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where('job_class', 'like', '%'.$search.'%')
                    ->orWhere('uuid', 'like', '%'.$search.'%');
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
