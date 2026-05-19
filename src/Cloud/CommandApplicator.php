<?php

namespace Deck\Deck\Cloud;

use Deck\Deck\Deck;
use Deck\Deck\Support\JobCancellation;

class CommandApplicator
{
    public function __construct(
        private readonly Deck $deck,
    ) {}

    public function apply(AgentCommand $command): AgentCommandResult
    {
        return match ($command->type) {
            'cancel_execution' => $this->cancelExecution($command),
            'force_cancel_execution' => $this->forceCancelExecution($command),
            'cancel_pending' => $this->cancelPending($command),
            default => $this->failed($command->id, 'Unknown command type: '.$command->type),
        };
    }

    private function cancelExecution(AgentCommand $command): AgentCommandResult
    {
        $uuid = $this->requiredString($command->payload, 'uuid');

        if ($uuid === null) {
            return $this->failed($command->id, 'Missing uuid in command payload.');
        }

        if (JobCancellation::isCancelled($uuid)) {
            return $this->ignored($command->id);
        }

        if ($this->deck->requestCancelExecution($uuid, $this->optionalInt($command->payload, 'attempt')) !== null) {
            return $this->applied($command->id);
        }

        return $this->failed($command->id, 'Execution not running.');
    }

    private function forceCancelExecution(AgentCommand $command): AgentCommandResult
    {
        $uuid = $this->requiredString($command->payload, 'uuid');

        if ($uuid === null) {
            return $this->failed($command->id, 'Missing uuid in command payload.');
        }

        if (JobCancellation::isCancelled($uuid)) {
            return $this->ignored($command->id);
        }

        if ($this->deck->forceCancelExecution($uuid, $this->optionalInt($command->payload, 'attempt')) !== null) {
            return $this->applied($command->id);
        }

        return $this->failed($command->id, 'Execution not running.');
    }

    private function cancelPending(AgentCommand $command): AgentCommandResult
    {
        $uuid = $this->requiredString($command->payload, 'uuid');

        if ($uuid === null) {
            return $this->failed($command->id, 'Missing uuid in command payload.');
        }

        if (JobCancellation::isCancelled($uuid)) {
            return $this->ignored($command->id);
        }

        $connection = $this->requiredString($command->payload, 'connection');
        $queue = $this->requiredString($command->payload, 'queue');

        if ($connection === null || $queue === null) {
            return $this->failed($command->id, 'Missing connection or queue in command payload.');
        }

        $this->deck->cancelPending($uuid, $connection, $queue, (bool) ($command->payload['force'] ?? false));

        return $this->applied($command->id);
    }

    private function applied(string $id): AgentCommandResult
    {
        return new AgentCommandResult(id: $id, status: 'applied');
    }

    private function ignored(string $id): AgentCommandResult
    {
        return new AgentCommandResult(id: $id, status: 'ignored');
    }

    private function failed(string $id, string $message): AgentCommandResult
    {
        return new AgentCommandResult(id: $id, status: 'failed', message: $message);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiredString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function optionalInt(array $payload, string $key): ?int
    {
        if (! array_key_exists($key, $payload)) {
            return null;
        }

        return (int) $payload[$key];
    }
}
