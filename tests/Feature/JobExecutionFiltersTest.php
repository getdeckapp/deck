<?php

use TorMorten\Deck\Enums\JobExecutionStatus;

it('filters activity by connection and tag', function () {
    createDeckExecution([
        'job_class' => 'App\\Jobs\\TaggedJob',
        'connection' => 'redis',
        'queue' => 'high',
        'tags' => ['billing'],
        'status' => JobExecutionStatus::Completed,
    ]);

    createDeckExecution([
        'job_class' => 'App\\Jobs\\OtherJob',
        'connection' => 'sync',
        'queue' => 'default',
        'tags' => ['ops'],
        'status' => JobExecutionStatus::Completed,
    ]);

    $this->get(route('deck.activity.index', ['connection' => 'redis', 'tag' => 'billing']))
        ->assertOk()
        ->assertSee('TaggedJob')
        ->assertDontSee('OtherJob');
});
