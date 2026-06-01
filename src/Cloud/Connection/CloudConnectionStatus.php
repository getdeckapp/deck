<?php

namespace Deck\Deck\Cloud\Connection;

readonly class CloudConnectionStatus
{
    public function __construct(
        public CloudConnectionState $state,
        public string $label,
        public string $detail,
        public string $host,
        public string $project,
        public string $environment,
        public bool $workersEnabled,
        public bool $commandsEnabled,
        public ?string $dashboardUrl = null,
    ) {}

    public function isEnabled(): bool
    {
        return $this->state !== CloudConnectionState::Disabled;
    }

    public function isHealthy(): bool
    {
        return $this->state === CloudConnectionState::Connected;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'label' => $this->label,
            'detail' => $this->detail,
            'host' => $this->host,
            'project' => $this->project,
            'environment' => $this->environment,
            'workers_enabled' => $this->workersEnabled,
            'commands_enabled' => $this->commandsEnabled,
            'dashboard_url' => $this->dashboardUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            state: CloudConnectionState::from((string) $data['state']),
            label: (string) $data['label'],
            detail: (string) $data['detail'],
            host: (string) $data['host'],
            project: (string) $data['project'],
            environment: (string) $data['environment'],
            workersEnabled: (bool) ($data['workers_enabled'] ?? false),
            commandsEnabled: (bool) ($data['commands_enabled'] ?? false),
            dashboardUrl: isset($data['dashboard_url']) && $data['dashboard_url'] !== ''
                ? (string) $data['dashboard_url']
                : null,
        );
    }
}
