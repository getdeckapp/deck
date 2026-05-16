<?php

namespace TorMorten\Deck\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use TorMorten\Deck\Support\DeckAlertChecker;

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

        $alerts = $checker->staleJobs();

        if ($alerts->isEmpty()) {
            $this->components->info('No stale job alerts.');

            return self::SUCCESS;
        }

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
