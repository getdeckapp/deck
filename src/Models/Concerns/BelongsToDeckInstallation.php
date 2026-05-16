<?php

namespace TorMorten\Deck\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use TorMorten\Deck\Support\DeckInstallation;

trait BelongsToDeckInstallation
{
    public function scopeForInstallation(Builder $query): Builder
    {
        return $query
            ->where('project', DeckInstallation::project())
            ->where('environment', DeckInstallation::environment());
    }
}
