<?php

namespace Deck\Deck\Models\Concerns;

use Deck\Deck\Core\DeckInstallation;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToDeckInstallation
{
    public function scopeForInstallation(Builder $query): Builder
    {
        return $query
            ->where('project', DeckInstallation::project())
            ->where('environment', DeckInstallation::environment());
    }
}
