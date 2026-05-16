<?php

namespace TorMorten\Deck\Support;

use Illuminate\Support\Collection;
use TorMorten\Deck\Models\JobExecution;

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
