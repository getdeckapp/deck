<?php

namespace Deck\Deck\Commands;

use Composer\InstalledVersions;
use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Core\DeckDatabase;
use Deck\Deck\Core\DeckInstallation;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Queue\DeckCallQueuedHandler;
use Deck\Deck\Recording\QueuedJobMetadata;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class DoctorCommand extends Command
{
    protected $signature = 'deck:doctor';

    protected $description = 'Diagnose why Deck is not recording job executions';

    public function handle(): int
    {
        $connection = DeckDatabase::connection() ?? (string) config('database.default');
        $executionsTable = (string) config('deck.tables.job_executions', 'deck_job_executions');
        $project = DeckInstallation::project();
        $environment = DeckInstallation::environment();

        $this->components->info('Deck doctor');
        $this->line('Package version: '.$this->packageVersion());
        $this->line("Database connection: {$connection}");
        $this->line("Executions table: {$executionsTable}");
        $this->line("Project filter: {$project}");
        $this->line("Environment filter: {$environment}");
        $this->line('Queue default: '.(string) config('queue.default'));
        $this->line('CallQueuedHandler: '.$this->callQueuedHandlerLabel());

        if (! DeckDatabase::schema()->hasTable($executionsTable)) {
            $this->components->error("Table [{$executionsTable}] does not exist on connection [{$connection}].");
            $this->line(filled(config('deck.database_connection'))
                ? 'Run: php artisan migrate --database='.config('deck.database_connection')
                : 'Run: php artisan migrate');

            return self::FAILURE;
        }

        $this->components->info('Table exists.');

        $processingListeners = count(Event::getListeners(JobProcessing::class));
        $processedListeners = count(Event::getListeners(JobProcessed::class));
        $failedListeners = count(Event::getListeners(JobFailed::class));
        $attemptedListeners = count(Event::getListeners(JobAttempted::class));

        $this->line("JobProcessing listeners: {$processingListeners}");
        $this->line("JobProcessed listeners: {$processedListeners}");
        $this->line("JobFailed listeners: {$failedListeners}");
        $this->line("JobAttempted listeners: {$attemptedListeners}");

        if ($processingListeners === 0) {
            $this->components->warn('No JobProcessing listeners are registered. Is DeckServiceProvider loaded?');
        }

        if ($processedListeners === 0) {
            $this->components->error('No JobProcessed listeners — completed jobs will not be recorded. Deploy a current deck/deck build and restart Horizon.');
        }

        if ($attemptedListeners === 0) {
            $this->components->warn('No JobAttempted listeners — terminal status fallback is disabled.');
        }

        $uuid = (string) Str::uuid();

        try {
            app(JobExecutionRecorder::class)->record(new JobExecutionRecord(
                metadata: new QueuedJobMetadata(
                    uuid: $uuid,
                    jobClass: self::class,
                    connection: 'doctor',
                    queue: 'doctor',
                    attempt: 1,
                    tags: null,
                ),
                project: $project,
                environment: $environment,
                status: JobExecutionStatus::Completed,
                startedAt: Carbon::now()->subSecond(),
                finishedAt: Carbon::now(),
                durationMs: 1000,
            ));

            $persisted = JobExecution::query()
                ->where('uuid', $uuid)
                ->where('project', $project)
                ->where('environment', $environment)
                ->exists();

            if (! $persisted) {
                $this->components->error('Test write did not persist. Check DECK_DB_CONNECTION vs where you inspect tables.');
                $this->line('Rows in table (any project/env): '.JobExecution::query()->count());

                return self::FAILURE;
            }

            JobExecution::query()->where('uuid', $uuid)->delete();

            $this->components->info('Test write succeeded on the Deck database connection.');
        } catch (\Throwable $exception) {
            $this->components->error('Test write failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($processedListeners > 0) {
            $this->simulateQueueRecording($project, $environment);
        }

        $scopedCount = JobExecution::query()->forInstallation()->count();
        $totalCount = JobExecution::query()->count();

        $this->line("Executions for {$project}/{$environment}: {$scopedCount}");
        $this->line("Executions on this connection (all scopes): {$totalCount}");

        if ($totalCount > 0 && $scopedCount === 0) {
            $this->components->warn('Rows exist but none match DECK_PROJECT / DECK_ENVIRONMENT. Adjust filters or .env.');
            $this->line('Sample project/env pairs in table:');
            JobExecution::query()
                ->select('project', 'environment')
                ->distinct()
                ->limit(5)
                ->get()
                ->each(fn ($row) => $this->line("  - {$row->project} / {$row->environment}"));
        }

        if ($processingListeners === 0 || $processedListeners === 0) {
            return self::FAILURE;
        }

        if ($scopedCount === 0 && $totalCount === 0) {
            $this->newLine();
            $this->components->warn('No execution rows from Horizon yet. Process a redis job, then recount.');
            $this->line('  php artisan tinker --execute \'echo Deck\\Deck\\Models\\JobExecution::query()->count();\'');
        }

        return self::SUCCESS;
    }

    private function simulateQueueRecording(string $project, string $environment): void
    {
        $uuid = (string) Str::uuid();
        $jobClass = DoctorProbeQueueJob::class;
        $queueJob = new DoctorProbeQueueJob($uuid, self::class);

        try {
            Event::dispatch(new JobProcessing('doctor', $queueJob));

            $running = JobExecution::query()
                ->where('uuid', $uuid)
                ->where('project', $project)
                ->where('environment', $environment)
                ->where('status', JobExecutionStatus::Running)
                ->exists();

            Event::dispatch(new JobProcessed('doctor', $queueJob));

            $completed = JobExecution::query()
                ->where('uuid', $uuid)
                ->where('project', $project)
                ->where('environment', $environment)
                ->where('status', JobExecutionStatus::Completed)
                ->exists();

            JobExecution::query()->where('uuid', $uuid)->delete();

            if ($running && $completed) {
                $this->components->info('Queue event listeners recorded a full processing → completed cycle.');

                return;
            }

            $this->components->warn('Queue listeners did not persist the probe job (running='.($running ? 'yes' : 'no').', completed='.($completed ? 'yes' : 'no').').');
        } catch (\Throwable $exception) {
            JobExecution::query()->where('uuid', $uuid)->delete();

            $this->components->error('Queue listener probe failed: '.$exception->getMessage());
        }
    }

    private function packageVersion(): string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled('deck/deck')) {
            return 'unknown';
        }

        return InstalledVersions::getPrettyVersion('deck/deck')
            ?? InstalledVersions::getVersion('deck/deck');
    }

    private function callQueuedHandlerLabel(): string
    {
        $handler = app(CallQueuedHandler::class);

        return $handler instanceof DeckCallQueuedHandler
            ? DeckCallQueuedHandler::class
            : $handler::class;
    }
}

/**
 * Minimal queue job stub used only by deck:doctor to exercise real event listeners.
 */
final class DoctorProbeQueueJob implements QueueJobContract
{
    public function __construct(
        private readonly string $uuid,
        private readonly string $jobClass,
        private readonly string $connection = 'doctor',
        private readonly string $queue = 'doctor',
        private readonly int $attempt = 1,
    ) {}

    public function uuid(): ?string
    {
        return $this->uuid;
    }

    public function getJobId(): string
    {
        return 'doctor-probe';
    }

    public function payload(): array
    {
        return [
            'uuid' => $this->uuid,
            'displayName' => $this->jobClass,
            'data' => ['commandName' => $this->jobClass],
        ];
    }

    public function fire(): void {}

    public function release($delay = 0): void {}

    public function isReleased(): bool
    {
        return false;
    }

    public function delete(): void {}

    public function isDeleted(): bool
    {
        return false;
    }

    public function isDeletedOrReleased(): bool
    {
        return true;
    }

    public function attempts(): int
    {
        return $this->attempt;
    }

    public function hasFailed(): bool
    {
        return false;
    }

    public function markAsFailed(): void {}

    public function fail($e = null): void {}

    public function maxTries(): ?int
    {
        return null;
    }

    public function maxExceptions(): ?int
    {
        return null;
    }

    public function timeout(): ?int
    {
        return null;
    }

    public function retryUntil(): ?int
    {
        return null;
    }

    public function getName(): string
    {
        return $this->jobClass;
    }

    public function resolveName(): string
    {
        return $this->jobClass;
    }

    public function resolveQueuedJobClass(): string
    {
        return $this->jobClass;
    }

    public function getConnectionName(): string
    {
        return $this->connection;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getRawBody(): string
    {
        return '';
    }
}
