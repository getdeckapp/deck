<?php

namespace TorMorten\Deck\Models;

use Illuminate\Database\Eloquent\Model;
use TorMorten\Deck\Data\JobProgressState;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\Concerns\BelongsToDeckInstallation;
use TorMorten\Deck\Models\Concerns\UsesDeckConnection;
use TorMorten\Deck\Support\DeckHorizon;
use TorMorten\Deck\Support\FormatDuration;
use TorMorten\Deck\Support\JobCancellation;
use TorMorten\Deck\Support\JobProgress;

class JobExecution extends Model
{
    use BelongsToDeckInstallation;
    use UsesDeckConnection;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => JobExecutionStatus::class,
            'tags' => 'array',
            'context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'attempt' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('deck.tables.job_executions', 'deck_job_executions');
    }

    public function formattedDuration(): string
    {
        return FormatDuration::format($this->duration_ms);
    }

    public function isLongRunning(): bool
    {
        if ($this->status !== JobExecutionStatus::Running) {
            return false;
        }

        $threshold = (int) config('deck.long_running_threshold_seconds', 300);

        return $this->started_at->diffInSeconds(now()) >= $threshold;
    }

    public function hasFailureDetails(): bool
    {
        return $this->status === JobExecutionStatus::Failed
            && ($this->exception_class !== null || $this->exception_message !== null);
    }

    public function canRetry(): bool
    {
        return $this->status === JobExecutionStatus::Failed;
    }

    public function progress(): ?JobProgressState
    {
        if ($this->status !== JobExecutionStatus::Running) {
            return null;
        }

        return JobProgress::get($this->uuid);
    }

    public function horizonFailedJobUrl(): ?string
    {
        if ($this->status !== JobExecutionStatus::Failed) {
            return null;
        }

        return DeckHorizon::failedJobUrl($this->uuid);
    }

    public function isCancellationPending(): bool
    {
        return $this->status === JobExecutionStatus::Running
            && JobCancellation::isCancelled($this->uuid);
    }

    public static function hasRunningForInstallation(): bool
    {
        return static::query()
            ->forInstallation()
            ->where('status', JobExecutionStatus::Running)
            ->exists();
    }

    public static function hasPendingCancellationsForInstallation(): bool
    {
        return static::query()
            ->forInstallation()
            ->where('status', JobExecutionStatus::Running)
            ->pluck('uuid')
            ->contains(fn (string $uuid): bool => JobCancellation::isCancelled($uuid));
    }

    /**
     * @return array{uuid: string, attempt: int}
     */
    public function activityRouteParameters(): array
    {
        return [
            'uuid' => $this->uuid,
            'attempt' => $this->attempt,
        ];
    }
}
