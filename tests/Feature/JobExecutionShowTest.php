<?php

use Deck\Deck\Enums\DispatchGroupSource;
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

it('shows observability context and related executions', function () {
    $parent = createDeckExecution([
        'job_class' => 'App\\Jobs\\ProcessStripeWebhook',
        'dispatch_group_id' => 'req-stripe-webhook-burst',
    ]);

    $execution = createDeckExecution([
        'job_class' => 'App\\Jobs\\SyncInvoices',
        'wait_ms' => 45_000,
        'dispatch_group_id' => 'req-stripe-webhook-burst',
        'dispatch_group_source' => DispatchGroupSource::Lineage,
        'parent_job_uuid' => $parent->uuid,
        'parent_job_class' => $parent->job_class,
        'dispatch_origin' => [
            'type' => 'job',
            'parent_uuid' => $parent->uuid,
            'parent_class' => $parent->job_class,
        ],
    ]);

    createDeckExecution([
        'job_class' => 'App\\Jobs\\ChargeSubscription',
        'dispatch_group_id' => 'req-stripe-webhook-burst',
        'parent_job_uuid' => $execution->uuid,
        'parent_job_class' => $execution->job_class,
    ]);

    $response = $this->get(route('deck.activity.show', $execution->activityRouteParameters()));

    $response->assertOk();
    $response->assertSee('Dispatch context');
    $response->assertSee('req-stripe-webhook-burst');
    $response->assertSee('ProcessStripeWebhook');
    $response->assertSee('Same dispatch group');
    $response->assertSee('Dispatched jobs');
    $response->assertSee('ChargeSubscription');
    $response->assertSee('Queue wait');
});
