<?php

namespace Deck\Deck\Cloud\Commands;

readonly class AgentCommand
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $payload,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        if (! isset($data['id'], $data['type']) || ! is_string($data['id']) || ! is_string($data['type'])) {
            return null;
        }

        $payload = $data['payload'] ?? null;

        if (! is_array($payload)) {
            return null;
        }

        return new self($data['id'], $data['type'], $payload);
    }
}
