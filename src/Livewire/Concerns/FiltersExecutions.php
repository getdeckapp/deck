<?php

namespace TorMorten\Deck\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobExecution;

trait FiltersExecutions
{
    protected function applyExecutionFilters(Builder $query): Builder
    {
        if (property_exists($this, 'search') && $this->search !== '') {
            $search = $this->search;

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where('job_class', 'like', '%'.$search.'%')
                    ->orWhere('uuid', 'like', '%'.$search.'%');
            });
        }

        if (property_exists($this, 'status') && $this->status !== '' && JobExecutionStatus::tryFrom($this->status)) {
            $query->where('status', $this->status);
        }

        if (property_exists($this, 'queue') && $this->queue !== '') {
            $query->where('queue', $this->queue);
        }

        if (property_exists($this, 'connection') && $this->connection !== '') {
            $query->where('connection', $this->connection);
        }

        if (property_exists($this, 'tag') && $this->tag !== '') {
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
