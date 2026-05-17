<?php

namespace TorMorten\Deck\Support;

use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Enums\QueueBusynessLevel;
use TorMorten\Deck\Models\JobExecution;

class QueueBusyness
{
    // Horizon overall score: 65% from the busiest queue, 35% from the average
    private const HORIZON_PEAK_WEIGHT = 0.65;

    private const HORIZON_AVERAGE_WEIGHT = 0.35;

    // Throughput pressure adds up to 20% of its raw score on top of the queue scores
    private const HORIZON_THROUGHPUT_DIVISOR = 5;

    // Deck overall score weights
    private const DECK_PEAK_WEIGHT = 0.55;

    private const DECK_AVERAGE_WEIGHT = 0.25;

    private const DECK_RUNNING_FACTOR = 10;

    private const DECK_RUNNING_CAP = 35;

    private const DECK_THROUGHPUT_FACTOR = 18;

    private const DECK_THROUGHPUT_CAP = 20;

    // Horizon per-queue scoring caps and factors
    private const HORIZON_BACKLOG_FACTOR = 6;

    private const HORIZON_BACKLOG_CAP = 45;

    private const HORIZON_WAIT_FACTOR = 4;

    private const HORIZON_WAIT_CAP = 35;

    private const HORIZON_CAPACITY_FACTOR = 4;

    private const HORIZON_CAPACITY_CAP = 20;

    // Deck per-queue scoring
    private const DECK_QUEUE_RUNNING_FACTOR = 22;

    private const DECK_QUEUE_THROUGHPUT_FACTOR = 22;

    private const DECK_QUEUE_THROUGHPUT_CAP = 35;

    private const DECK_QUEUE_COMPLETED_FACTOR = 2;

    private const DECK_QUEUE_COMPLETED_CAP = 25;

    private const DECK_QUEUE_LOAD_DIVISOR = 2;

    private const DECK_QUEUE_LOAD_CAP = 15;

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
            : (int) min(100, round((max($scores) * self::HORIZON_PEAK_WEIGHT) + (collect($scores)->avg() * self::HORIZON_AVERAGE_WEIGHT)));

        $overall = min(100, $overall + $this->scoreThroughputPressure($summary) / self::HORIZON_THROUGHPUT_DIVISOR);

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
            ($peak * self::DECK_PEAK_WEIGHT)
            + ($average * self::DECK_AVERAGE_WEIGHT)
            + min(self::DECK_RUNNING_CAP, $running * self::DECK_RUNNING_FACTOR)
            + ($throughputRatio > 1 ? min(self::DECK_THROUGHPUT_CAP, ($throughputRatio - 1) * self::DECK_THROUGHPUT_FACTOR) : 0)
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

        $backlogScore = min(self::HORIZON_BACKLOG_CAP, (int) round(sqrt($length) * self::HORIZON_BACKLOG_FACTOR));
        $waitScore = min(self::HORIZON_WAIT_CAP, (int) round($waitSeconds / self::HORIZON_WAIT_FACTOR));
        $capacityScore = min(self::HORIZON_CAPACITY_CAP, (int) round(($length / $processes) * self::HORIZON_CAPACITY_FACTOR));

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
            ($queue['running'] * self::DECK_QUEUE_RUNNING_FACTOR)
            + min(self::DECK_QUEUE_THROUGHPUT_CAP, (int) round(max(0, $throughputRatio - 1) * self::DECK_QUEUE_THROUGHPUT_FACTOR))
            + min(self::DECK_QUEUE_COMPLETED_CAP, $queue['completed_last_hour'] * self::DECK_QUEUE_COMPLETED_FACTOR)
            + min(self::DECK_QUEUE_LOAD_CAP, (int) round($queue['load'] / self::DECK_QUEUE_LOAD_DIVISOR))
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
