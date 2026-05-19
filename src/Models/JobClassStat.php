<?php

namespace Deck\Deck\Models;

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\Concerns\BelongsToDeckInstallation;
use Deck\Deck\Models\Concerns\UsesDeckConnection;
use Deck\Deck\Support\FormatDuration;
use Illuminate\Database\Eloquent\Model;

class JobClassStat extends Model
{
    use BelongsToDeckInstallation;
    use UsesDeckConnection;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_started_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'last_status' => JobExecutionStatus::class,
            'last_duration_ms' => 'integer',
            'success_count' => 'integer',
            'failure_count' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('deck.tables.job_class_stats', 'deck_job_class_stats');
    }

    public function formattedLastDuration(): string
    {
        return FormatDuration::format($this->last_duration_ms);
    }

    public function successRate(): ?float
    {
        $total = $this->success_count + $this->failure_count;

        if ($total === 0) {
            return null;
        }

        return round(($this->success_count / $total) * 100, 1);
    }
}
