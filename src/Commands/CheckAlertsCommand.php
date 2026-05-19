<?php

namespace Deck\Deck\Commands;

use Deck\Deck\Support\DeckAlertChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckAlertsCommand extends Command
{
    protected $signature = 'deck:check-alerts';

    protected $description = 'Check configured Deck stale-job rules and send alerts';

    public function handle(DeckAlertChecker $checker): int
    {
        if (! config('deck.alerts.enabled', false)) {
            $this->components->info('Deck alerts are disabled (DECK_ALERTS_ENABLED=false).');

            return self::SUCCESS;
        }

        $staleJobAlerts = $checker->staleJobs();
        $failureRateAlerts = $checker->failureRates();
        $unprocessedQueueAlerts = $checker->unprocessedQueues();

        if ($staleJobAlerts->isEmpty() && $failureRateAlerts->isEmpty() && $unprocessedQueueAlerts->isEmpty()) {
            $this->components->info('No Deck alerts.');

            return self::SUCCESS;
        }

        if ($unprocessedQueueAlerts->isNotEmpty()) {
            $this->components->warn(sprintf(
                '%d queue(s) have pending jobs without Horizon workers:',
                $unprocessedQueueAlerts->count(),
            ));

            foreach ($unprocessedQueueAlerts as $alert) {
                $queue = $alert->queue;
                $this->components->warn(sprintf(
                    '- %s:%s (%d pending)',
                    $queue->connection,
                    $queue->queue,
                    $queue->pending,
                ));
            }
        }

        if ($failureRateAlerts->isNotEmpty()) {
            $this->components->warn(sprintf(
                '%d job class(es) exceeded the configured failure-rate threshold:',
                $failureRateAlerts->count(),
            ));

            foreach ($failureRateAlerts as $alert) {
                $this->components->warn(sprintf(
                    '- %s (%.1f%% failed, max %.1f%%, %d samples / %dh)',
                    $alert->jobClass,
                    $alert->failureRate,
                    $alert->maxFailureRate,
                    $alert->sampleCount,
                    $alert->windowHours,
                ));
            }
        }

        if ($staleJobAlerts->isEmpty()) {
            return self::SUCCESS;
        }

        $alerts = $staleJobAlerts;

        $notificationClass = config('deck.alerts.notification');
        $notifiableClass = config('deck.alerts.notifiable');

        if (! is_string($notificationClass) || $notificationClass === '' || ! class_exists($notificationClass)) {
            $this->components->warn('Stale jobs detected, but deck.alerts.notification is not configured.');

            foreach ($alerts as $alert) {
                $this->components->warn(sprintf(
                    '- %s (last finished: %s, max age: %dh)',
                    $alert->jobClass,
                    $alert->lastFinishedAt?->toDateTimeString() ?? 'never',
                    $alert->maxAgeHours,
                ));
            }

            return self::SUCCESS;
        }

        if (! is_string($notifiableClass) || $notifiableClass === '' || ! class_exists($notifiableClass)) {
            $this->components->warn('Stale jobs detected, but deck.alerts.notifiable is not configured.');

            return self::SUCCESS;
        }

        $notifiable = app($notifiableClass);

        Notification::send([$notifiable], new $notificationClass($alerts));

        $this->components->info(sprintf('Sent %d stale job alert(s).', $alerts->count()));

        return self::SUCCESS;
    }
}
