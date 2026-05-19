<?php

namespace Deck\Deck\Cloud;

use Illuminate\Support\Facades\Log;
use Throwable;

class CommandPoller
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly CommandApplicator $applicator,
    ) {}

    public function poll(): void
    {
        if (! DeckCloud::commandsEnabled()) {
            return;
        }

        try {
            $commands = $this->pull();

            if ($commands === []) {
                return;
            }

            $this->ack($this->apply($commands));
        } catch (Throwable $exception) {
            if (config('deck.cloud.log_failures', true)) {
                Log::warning('Deck Cloud command poll failed.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return list<AgentCommand>
     */
    private function pull(): array
    {
        $response = $this->http->get('/api/v1/agent/commands', [
            ...DeckCloud::installationIdentity(),
            'limit' => 50,
        ]);

        if ($response === null) {
            return [];
        }

        $commands = $response['commands'] ?? [];

        if (! is_array($commands)) {
            return [];
        }

        $parsed = [];
        $seenIds = [];

        foreach ($commands as $command) {
            if (! is_array($command)) {
                continue;
            }

            $agentCommand = AgentCommand::fromArray($command);

            if ($agentCommand === null || isset($seenIds[$agentCommand->id])) {
                continue;
            }

            $seenIds[$agentCommand->id] = true;
            $parsed[] = $agentCommand;
        }

        return $parsed;
    }

    /**
     * @param  list<AgentCommand>  $commands
     * @return list<AgentCommandResult>
     */
    private function apply(array $commands): array
    {
        $results = [];

        foreach ($commands as $command) {
            $results[] = $this->applicator->apply($command);
        }

        return $results;
    }

    /**
     * @param  list<AgentCommandResult>  $results
     */
    private function ack(array $results): void
    {
        if ($results === []) {
            return;
        }

        $this->http->post('/api/v1/agent/commands/ack', [
            ...DeckCloud::installationIdentity(),
            'results' => array_map(
                fn (AgentCommandResult $result): array => $result->toArray(),
                $results,
            ),
        ]);
    }
}
