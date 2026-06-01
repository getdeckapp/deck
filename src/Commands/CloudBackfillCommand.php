<?php

namespace Deck\Deck\Commands;

use Deck\Deck\Cloud\Connection\HttpClient;
use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Cloud\Events\CloudExecutionBackfillPayload;
use Deck\Deck\Models\JobExecution;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CloudBackfillCommand extends Command
{
    protected $signature = 'deck:cloud-backfill
                            {--chunk=100 : Events per ingest request}
                            {--since= : Only include executions started on/after this date}
                            {--until= : Only include executions started before this date}
                            {--project= : Filter by project slug}
                            {--environment= : Filter by environment slug}
                            {--dry-run : Count events without sending them}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Backfill local Deck execution history to Deck Cloud via the ingest API';

    public function handle(HttpClient $http): int
    {
        if (! DeckCloud::isEnabled()) {
            $this->components->warn('Deck Cloud is disabled (set DECK_API_KEY, or remove DECK_CLOUD_ENABLED=false).');

            return self::SUCCESS;
        }

        $query = JobExecution::query()->forInstallation()->orderBy('id');
        $since = $this->option('since');
        $until = $this->option('until');
        $project = $this->option('project');
        $environment = $this->option('environment');

        if (is_string($since) && $since !== '') {
            $query->where('started_at', '>=', Carbon::parse($since));
        }

        if (is_string($until) && $until !== '') {
            $query->where('started_at', '<', Carbon::parse($until));
        }

        if (is_string($project) && $project !== '') {
            $query->where('project', DeckCloud::slug($project));
        }

        if (is_string($environment) && $environment !== '') {
            $query->where('environment', DeckCloud::slug($environment));
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->components->info('No executions matched the backfill filters.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info("Dry run: {$total} execution(s) would be sent to Deck Cloud.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Send {$total} execution(s) to Deck Cloud?", true)) {
            return self::SUCCESS;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $sent = 0;
        $failedBatches = 0;

        $query->chunkById($chunkSize, function ($executions) use ($http, &$sent, &$failedBatches): void {
            /** @var list<array<string, mixed>> $events */
            $events = $executions
                ->map(fn (JobExecution $execution): array => CloudExecutionBackfillPayload::fromExecution($execution))
                ->values()
                ->all();

            if ($http->post(DeckCloud::EventsIngestPath, ['events' => $events])) {
                $sent += count($events);
                $this->output->write('.');

                return;
            }

            $failedBatches++;
            $this->output->write('x');
        });

        $this->newLine();
        $this->components->info("Backfill complete: {$sent} event(s) sent.");

        if ($failedBatches > 0) {
            $this->components->warn("{$failedBatches} batch(es) failed — check logs and retry (ingest is idempotent).");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
