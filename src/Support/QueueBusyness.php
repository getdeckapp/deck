<?php

namespace Deck\Deck\Support;

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Enums\QueueBusynessLevel;
use Deck\Deck\Models\JobExecution;

class QueueBusyness
{
    public function __construct(
        private readonly HorizonSnapshot $horizon,
        private readonly QueueInsights $insights,
    ) {}

    /**
     * @return array{
     *     score: int,
     *     level: QueueBusynessLevel,
     *     label: string,
     *     summary: string,
     *     source: string,
     *     queues: list<array{name: string, score: int, level: QueueBusynessLevel, detail: string}>
     * }
     */
    public function assess(): array
    {
        if ($this->horizon->isAvailable()) {
            return $this->assessFromHorizon();
        }

        return $this->assessFromDeckLog();
    }

    /**
     * @return array{
     *     score: int,
     *     level: QueueBusynessLevel,
     *     label: string,
     *     summary: string,
     *     source: string,
     *     queues: list<array{name: string, score: int, level: QueueBusynessLevel, detail: string}>
     * }
     */
    private function assessFromHorizon(): array
    {
        $summary = $this->horizon->summary();
        $workload = $this->horizon->workload();

        if ($summary === null) {
            return $this->emptyAssessment('deck');
        }

        if ($summary['status'] === 'inactive') {
            return $this->buildAssessment(
                score: 5,
                level: QueueBusynessLevel::Idle,
                summary: 'Horizon is not running — no workers are processing jobs.',
                source: 'horizon',
                queues: [],
            );
        }

        if ($summary['status'] === 'paused') {
            return $this->buildAssessment(
                score: 45,
                level: QueueBusynessLevel::Moderate,
                summary: 'Horizon is paused. Jobs may be piling up in Redis.',
                source: 'horizon',
                queues: $this->mapHorizonQueueScores($workload),
            );
        }

        $queueScores = $this->mapHorizonQueueScores($workload);
        $scores = array_column($queueScores, 'score');

        $overall = $scores === []
            ? $this->scoreThroughputPressure($summary)
            : (int) min(100, round((max($scores) * 0.65) + (collect($scores)->avg() * 0.35)));

        $overall = min(100, $overall + $this->scoreThroughputPressure($summary) / 5);

        return $this->buildAssessment(
            score: $overall,
            level: $this->levelFromScore($overall),
            summary: $this->summarizeHorizon($overall, $summary, $workload),
            source: 'horizon',
            queues: $queueScores,
        );
    }

    /**
     * @return array{
     *     score: int,
     *     level: QueueBusynessLevel,
     *     label: string,
     *     summary: string,
     *     source: string,
     *     queues: list<array{name: string, score: int, level: QueueBusynessLevel, detail: string}>
     * }
     */
    private function assessFromDeckLog(): array
    {
        $insights = $this->insights->busyQueues();

        if ($insights->isEmpty()) {
            return $this->emptyAssessment('deck');
        }

        $running = (int) $insights->sum('running');
        $completedLastHour = (int) $insights->sum('completed_last_hour');
        $hourlyBaseline = $this->hourlyCompletionBaseline();
        $throughputRatio = $hourlyBaseline > 0
            ? $completedLastHour / $hourlyBaseline
            : ($completedLastHour > 0 ? 2.0 : 0.0);

        $queueScores = $insights
            ->take(12)
            ->map(fn (array $queue): array => $this->scoreDeckQueue($queue, $hourlyBaseline, $insights->count()))
            ->sortByDesc('score')
            ->values()
            ->all();

        $scores = array_column($queueScores, 'score');
        $peak = $scores === [] ? 0 : max($scores);
        $average = $scores === [] ? 0 : collect($scores)->avg();

        $overall = (int) min(100, round(
            ($peak * 0.55)
            + ($average * 0.25)
            + min(35, $running * 10)
            + ($throughputRatio > 1 ? min(20, ($throughputRatio - 1) * 18) : 0)
        ));

        return $this->buildAssessment(
            score: $overall,
            level: $this->levelFromScore($overall),
            summary: $this->summarizeDeck($overall, $running, $completedLastHour, $throughputRatio),
            source: 'deck',
            queues: $queueScores,
        );
    }

    /**
     * @param  list<array{name: string, length: int, wait: int|float, processes: int}>  $workload
     * @return list<array{name: string, score: int, level: QueueBusynessLevel, detail: string}>
     */
    private function mapHorizonQueueScores(array $workload): array
    {
        return collect($workload)
            ->map(function (array $queue): array {
                $score = $this->scoreHorizonQueue($queue);

                return [
                    'name' => $queue['name'],
                    'score' => $score,
                    'level' => $this->levelFromScore($score),
                    'detail' => sprintf(
                        '%s waiting · %d workers%s',
                        number_format($queue['length']),
                        $queue['processes'],
                        $queue['wait'] > 0
                            ? ' · ~'.FormatDuration::format((int) round((float) $queue['wait'] * 1000)).' wait'
                            : '',
                    ),
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * @param  array{name: string, length: int, wait: int|float, processes: int}  $queue
     */
    private function scoreHorizonQueue(array $queue): int
    {
        $processes = max(1, $queue['processes']);
        $length = $queue['length'];
        $waitSeconds = (float) $queue['wait'];

        $backlogScore = min(45, (int) round(sqrt($length) * 6));
        $waitScore = min(35, (int) round($waitSeconds / 4));
        $capacityScore = min(20, (int) round(($length / $processes) * 4));

        return min(100, $backlogScore + $waitScore + $capacityScore);
    }

    /**
     * @param  array{queue: string, running: int, completed_last_hour: int, avg_duration_ms: int|null, load: int}  $queue
     * @return array{name: string, score: int, level: QueueBusynessLevel, detail: string}
     */
    private function scoreDeckQueue(array $queue, float $hourlyBaseline, int $queueCount): array
    {
        $queueBaseline = $hourlyBaseline > 0 ? $hourlyBaseline / max(1, $queueCount) : 0.0;
        $throughputRatio = $queueBaseline > 0
            ? $queue['completed_last_hour'] / $queueBaseline
            : ($queue['completed_last_hour'] > 0 ? 2.0 : 0.0);

        $score = (int) min(100, round(
            ($queue['running'] * 22)
            + min(35, (int) round(max(0, $throughputRatio - 1) * 22))
            + min(25, $queue['completed_last_hour'] * 2)
            + min(15, (int) round($queue['load'] / 2))
        ));

        return [
            'name' => $queue['queue'],
            'score' => $score,
            'level' => $this->levelFromScore($score),
            'detail' => sprintf(
                '%d running · %d completed (1h)%s',
                $queue['running'],
                $queue['completed_last_hour'],
                $queue['avg_duration_ms'] !== null
                    ? ' · avg '.FormatDuration::format($queue['avg_duration_ms'])
                    : '',
            ),
        ];
    }

    /**
     * @param  array{status: string, processes: int, jobs_per_minute: int, paused_masters: int, wait: array<string, int|float>}  $summary
     */
    private function scoreThroughputPressure(array $summary): int
    {
        $processes = max(1, $summary['processes']);
        $jobsPerProcess = $summary['jobs_per_minute'] / $processes;

        return min(25, (int) round($jobsPerProcess / 2));
    }

    private function hourlyCompletionBaseline(): float
    {
        $completedLastDay = JobExecution::query()
            ->forInstallation()
            ->where('status', JobExecutionStatus::Completed)
            ->where('started_at', '>=', now()->subDay())
            ->count();

        return $completedLastDay > 0 ? $completedLastDay / 24 : 0.0;
    }

    private function levelFromScore(int $score): QueueBusynessLevel
    {
        return match (true) {
            $score <= 12 => QueueBusynessLevel::Idle,
            $score <= 30 => QueueBusynessLevel::Light,
            $score <= 50 => QueueBusynessLevel::Moderate,
            $score <= 70 => QueueBusynessLevel::Busy,
            default => QueueBusynessLevel::Critical,
        };
    }

    /**
     * @param  list<array{name: string, score: int, level: QueueBusynessLevel, detail: string}>  $queues
     * @return array{
     *     score: int,
     *     level: QueueBusynessLevel,
     *     label: string,
     *     summary: string,
     *     source: string,
     *     queues: list<array{name: string, score: int, level: QueueBusynessLevel, detail: string}>
     * }
     */
    private function buildAssessment(
        int $score,
        QueueBusynessLevel $level,
        string $summary,
        string $source,
        array $queues,
    ): array {
        return [
            'score' => $score,
            'level' => $level,
            'label' => $level->label(),
            'summary' => $summary,
            'source' => $source,
            'queues' => $queues,
        ];
    }

    /**
     * @return array{
     *     score: int,
     *     level: QueueBusynessLevel,
     *     label: string,
     *     summary: string,
     *     source: string,
     *     queues: list<array{name: string, score: int, level: QueueBusynessLevel, detail: string}>
     * }
     */
    private function emptyAssessment(string $source): array
    {
        return $this->buildAssessment(
            score: 0,
            level: QueueBusynessLevel::Idle,
            summary: 'No queue activity recorded yet.',
            source: $source,
            queues: [],
        );
    }

    /**
     * @param  list<array{name: string, length: int, wait: int|float, processes: int}>  $workload
     */
    private function summarizeHorizon(int $score, array $summary, array $workload): string
    {
        $pending = (int) collect($workload)->sum('length');

        if ($score >= 70) {
            return "Queues are under heavy pressure with {$pending} jobs waiting and high worker load.";
        }

        if ($score >= 50) {
            return "Workers are busy — {$pending} jobs pending across ".count($workload).' queue(s).';
        }

        if ($score >= 30) {
            return 'Moderate activity. Throughput is '.number_format($summary['jobs_per_minute']).' jobs/min across '.$summary['processes'].' processes.';
        }

        if ($pending > 0) {
            return "Light load — {$pending} job(s) waiting, workers are keeping up.";
        }

        return 'Queues are quiet. Workers are available with little or no backlog.';
    }

    private function summarizeDeck(int $score, int $running, int $completedLastHour, float $throughputRatio): string
    {
        if ($score >= 70) {
            return "High pressure — {$running} running and {$completedLastHour} completed in the last hour.";
        }

        if ($score >= 50) {
            return 'Busy period — throughput is '.number_format($throughputRatio, 1).'× the 24-hour average.';
        }

        if ($running > 0) {
            return "{$running} job(s) running now with {$completedLastHour} completed in the last hour.";
        }

        if ($completedLastHour > 0) {
            return "Light activity — {$completedLastHour} job(s) completed in the last hour.";
        }

        return 'Queues look idle based on recent execution history.';
    }
}
