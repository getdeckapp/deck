<?php

namespace Deck\Deck\Livewire;

use Deck\Deck\Models\JobClassStat;
use Deck\Deck\Models\JobExecution;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    #[On('deck-search-open')]
    public function resetQuery(): void
    {
        $this->query = '';
    }

    #[Computed]
    public function results(): array
    {
        if (strlen($this->query) < 2) {
            return ['classes' => collect(), 'executions' => collect()];
        }

        $classes = JobClassStat::query()
            ->forInstallation()
            ->where('job_class', 'like', '%'.$this->query.'%')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $executions = JobExecution::query()
            ->forInstallation()
            ->where(function ($query): void {
                $query->where('uuid', 'like', '%'.$this->query.'%')
                    ->orWhere('job_class', 'like', '%'.$this->query.'%')
                    ->orWhere('dispatch_group_id', 'like', '%'.$this->query.'%')
                    ->orWhere('parent_job_uuid', 'like', '%'.$this->query.'%')
                    ->orWhere('parent_job_class', 'like', '%'.$this->query.'%');
            })
            ->orderByDesc('started_at')
            ->limit(8)
            ->get();

        return compact('classes', 'executions');
    }

    public function render(): View
    {
        return view('deck::livewire.global-search');
    }
}
