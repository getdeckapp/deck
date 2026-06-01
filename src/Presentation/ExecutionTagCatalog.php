<?php

namespace Deck\Deck\Presentation;

use Deck\Deck\Models\JobExecution;
use Illuminate\Support\Collection;

class ExecutionTagCatalog
{
    /**
     * @return Collection<int, string>
     */
    public function tags(): Collection
    {
        return JobExecution::query()
            ->forInstallation()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->map(fn ($tag) => (string) $tag)
            ->unique()
            ->sort()
            ->values();
    }
}
