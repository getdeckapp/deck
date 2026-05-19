<?php

namespace Deck\Deck\Livewire;

use Deck\Deck\Livewire\Concerns\InteractsWithActionConfirmation;
use Deck\Deck\Support\DeckHorizon;
use Deck\Deck\Support\DeckPolling;
use Deck\Deck\Support\HorizonSnapshot;
use Deck\Deck\Support\QueueAdmin;
use Deck\Deck\Support\QueueInsights;
use Deck\Deck\Support\UnprocessedQueueDetector;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('deck::layouts.app')]
class WorkersIndex extends Component
{
    use InteractsWithActionConfirmation;

    public function confirmClearQueue(string $connection, string $queue): void
    {
        $this->requestConfirmation(
            'clearQueue',
            [$connection, $queue],
            'Clear queue',
            "Remove all pending jobs waiting on {$connection}:{$queue}? Reserved and in-flight jobs are not removed. This cannot be undone.",
            'Clear queue',
            'Clearing…',
            'danger',
        );
    }

    public function clearQueue(string $connection, string $queue): void
    {
        $result = QueueAdmin::clear($connection, $queue);

        session()->flash('status', $result->message);
    }

    public function render()
    {
        $horizon = app(HorizonSnapshot::class);

        return view('deck::livewire.workers-index', [
            'horizonAvailable' => $horizon->isAvailable(),
            'horizonSummary' => $horizon->summary(),
            'horizonWorkload' => $horizon->workload(),
            'horizonMasters' => $horizon->masters(),
            'horizonSupervisors' => $horizon->supervisors(),
            'queueInsights' => app(QueueInsights::class)->busyQueues(),
            'unprocessedQueues' => app(UnprocessedQueueDetector::class)->detect(),
            'shouldPoll' => $horizon->isAvailable(),
            'pollSeconds' => DeckPolling::workersSeconds(),
            'horizonUrl' => DeckHorizon::dashboardUrl(),
            'queueAdminEnabled' => (bool) config('deck.queue_admin.enabled', true),
        ]);
    }
}
