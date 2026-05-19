<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Livewire\JobExecutionIndex;
use Livewire\Livewire;

it('always enables polling on the activity feed', function () {
    createDeckExecution([
        'status' => JobExecutionStatus::Completed,
    ]);

    Livewire::test(JobExecutionIndex::class)
        ->assertViewHas('shouldPoll', true)
        ->assertSeeHtml('wire:poll.3s');
});

it('polls the activity feed faster while jobs are running', function () {
    createDeckExecution([
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    Livewire::test(JobExecutionIndex::class)
        ->assertViewHas('pollSeconds', 2)
        ->assertSeeHtml('wire:poll.2s');
});
