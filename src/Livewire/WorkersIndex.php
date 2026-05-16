<?php

namespace TorMorten\Deck\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use TorMorten\Deck\Support\DeckHorizon;
use TorMorten\Deck\Support\HorizonSnapshot;
use TorMorten\Deck\Support\QueueInsights;
use TorMorten\Deck\Support\UnprocessedQueueDetector;

#[Layout('deck::layouts.app')]
class WorkersIndex extends Component
{
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
            'horizonUrl' => DeckHorizon::dashboardUrl(),
        ]);
    }
}
