<?php

namespace Deck\Deck\Cloud\Commands;

readonly class AgentCommandResult
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $message = null,
        public ?string $appliedAt = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'status' => $this->status,
            'applied_at' => $this->appliedAt ?? now()->utc()->toIso8601String(),
        ];

        if ($this->message !== null && $this->message !== '') {
            $result['message'] = $this->message;
        }

        return $result;
    }
}
