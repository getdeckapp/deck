<?php

namespace Deck\Deck\Commands;

use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Support\DeckDatabase;
use Deck\Deck\Support\DeckInstallation;
use Deck\Deck\Support\QueuedJobMetadata;
use Illuminate\Console\Command;
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
        $this->line("Database connection: {$connection}");
        $this->line("Executions table: {$executionsTable}");
        $this->line("Project filter: {$project}");
        $this->line("Environment filter: {$environment}");

        if (! DeckDatabase::schema()->hasTable($executionsTable)) {
            $this->components->error("Table [{$executionsTable}] does not exist on connection [{$connection}].");
            $this->line(filled(config('deck.database_connection'))
                ? 'Run: php artisan migrate --database='.config('deck.database_connection')
                : 'Run: php artisan migrate');

            return self::FAILURE;
        }

        $this->components->info('Table exists.');

        $beforeListeners = count(Event::getListeners(JobProcessing::class));
        $this->line("JobProcessing listeners: {$beforeListeners}");

        if ($beforeListeners === 0) {
            $this->components->warn('No JobProcessing listeners are registered. Is DeckServiceProvider loaded?');
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

        if ($beforeListeners === 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
