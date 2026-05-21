<?php

namespace Deck\Deck\Cloud;

use Deck\Deck\Support\DeckInstallation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CloudConnectionProbe
{
    public function status(bool $fresh = false): CloudConnectionStatus
    {
        if (! DeckCloud::isEnabled()) {
            return $this->disabled();
        }

        $identity = DeckCloud::installationIdentity();
        $cacheKey = 'deck.cloud.connection:'.$identity['project'].':'.$identity['environment'];

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $ttl = max(15, (int) config('deck.cloud.probe_cache_seconds', 60));

        if (! $fresh) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return CloudConnectionStatus::fromArray($cached);
            }

            if ($cached !== null) {
                Cache::forget($cacheKey);
            }
        }

        $status = $this->probe();

        Cache::put($cacheKey, $status->toArray(), $ttl);

        return $status;
    }

    public function forget(): void
    {
        $identity = DeckCloud::installationIdentity();

        Cache::forget('deck.cloud.connection:'.$identity['project'].':'.$identity['environment']);
    }

    private function disabled(): CloudConnectionStatus
    {
        return new CloudConnectionStatus(
            state: CloudConnectionState::Disabled,
            label: 'Not configured',
            detail: 'Set DECK_API_KEY to connect to Deck Cloud.',
            host: '',
            project: DeckCloud::slug(DeckInstallation::project()),
            environment: DeckCloud::slug(DeckInstallation::environment()),
            workersEnabled: false,
            commandsEnabled: false,
        );
    }

    private function probe(): CloudConnectionStatus
    {
        $identity = DeckCloud::installationIdentity();
        $host = parse_url(DeckCloud::baseUrl(), PHP_URL_HOST) ?: DeckCloud::baseUrl();
        $dashboardUrl = DeckCloud::baseUrl();

        try {
            $response = Http::timeout(max(1, (int) config('deck.cloud.timeout_seconds', 5)))
                ->withToken((string) config('deck.cloud.api_key'))
                ->acceptJson()
                ->get(DeckCloud::baseUrl().'/'.ltrim(DeckCloud::CommandsPullPath, '/'), [
                    ...$identity,
                    'limit' => 1,
                ]);

            if ($response->successful()) {
                return new CloudConnectionStatus(
                    state: CloudConnectionState::Connected,
                    label: 'Connected',
                    detail: 'Agent API reachable for this installation.',
                    host: (string) $host,
                    project: $identity['project'],
                    environment: $identity['environment'],
                    workersEnabled: DeckCloud::workersEnabled(),
                    commandsEnabled: DeckCloud::commandsEnabled(),
                    dashboardUrl: $dashboardUrl,
                );
            }

            if (in_array($response->status(), [401, 403], true)) {
                return new CloudConnectionStatus(
                    state: CloudConnectionState::Unauthorized,
                    label: 'Invalid API key',
                    detail: 'Deck Cloud rejected the agent token (HTTP '.$response->status().').',
                    host: (string) $host,
                    project: $identity['project'],
                    environment: $identity['environment'],
                    workersEnabled: DeckCloud::workersEnabled(),
                    commandsEnabled: DeckCloud::commandsEnabled(),
                    dashboardUrl: $dashboardUrl,
                );
            }

            if ($response->status() === 404) {
                return new CloudConnectionStatus(
                    state: CloudConnectionState::Misconfigured,
                    label: 'Installation missing',
                    detail: 'Create project "'.$identity['project'].'" with environment "'.$identity['environment'].'" on Deck Cloud, or run deck:report-workers once.',
                    host: (string) $host,
                    project: $identity['project'],
                    environment: $identity['environment'],
                    workersEnabled: DeckCloud::workersEnabled(),
                    commandsEnabled: DeckCloud::commandsEnabled(),
                    dashboardUrl: $dashboardUrl,
                );
            }

            return new CloudConnectionStatus(
                state: CloudConnectionState::Unreachable,
                label: 'Unreachable',
                detail: 'Deck Cloud returned HTTP '.$response->status().'.',
                host: (string) $host,
                project: $identity['project'],
                environment: $identity['environment'],
                workersEnabled: DeckCloud::workersEnabled(),
                commandsEnabled: DeckCloud::commandsEnabled(),
                dashboardUrl: $dashboardUrl,
            );
        } catch (Throwable $exception) {
            return new CloudConnectionStatus(
                state: CloudConnectionState::Unreachable,
                label: 'Unreachable',
                detail: $exception->getMessage(),
                host: (string) $host,
                project: $identity['project'],
                environment: $identity['environment'],
                workersEnabled: DeckCloud::workersEnabled(),
                commandsEnabled: DeckCloud::commandsEnabled(),
                dashboardUrl: $dashboardUrl,
            );
        }
    }
}
