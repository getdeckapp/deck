<?php

use Deck\Deck\Enums\JobExecutionStatus;

it('uses job uuid and attempt in the activity show url', function () {
    $execution = createDeckExecution();

    $url = route('deck.activity.show', $execution->activityRouteParameters());

    expect($url)->toEndWith('/activity/'.$execution->uuid.'/'.$execution->attempt)
        ->and($url)->not->toMatch('#/activity/\d+$#');
});

it('renders execution details with stack trace for failed jobs', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Failed,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'Something went wrong',
        'exception_trace' => "#0 /app/Jobs/Example.php(12): Example->handle()\n#1 {main}",
    ]);

    $response = $this->get(route('deck.activity.show', $execution->activityRouteParameters()));

    $response->assertOk();
    $response->assertSee('Execution details');
    $response->assertSee('Stack trace');
    $response->assertSee('Something went wrong');
    $response->assertSee('Example.php(12)');
});

it('does not show failure section for completed executions', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Completed,
    ]);

    $response = $this->get(route('deck.activity.show', $execution->activityRouteParameters()));

    $response->assertOk();
    $response->assertDontSee('Stack trace');
    $response->assertSee('Execution details');
});

it('scopes execution details to the current installation', function () {
    $execution = createDeckExecution([
        'project' => 'other-app',
        'status' => JobExecutionStatus::Failed,
    ]);

    $this->get(route('deck.activity.show', $execution->activityRouteParameters()))->assertNotFound();
});
