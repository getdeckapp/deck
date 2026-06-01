<?php

use Deck\Deck\Enums\DispatchGroupSource;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Presentation\ExecutionObservability;

it('detects when an execution has observability data', function (): void {
    $execution = new JobExecution([
        'dispatch_group_id' => 'req-abc',
    ]);

    expect(ExecutionObservability::hasObservability($execution))->toBeTrue();
});

it('formats dispatch group source labels', function (): void {
    expect(ExecutionObservability::groupSourceLabel(DispatchGroupSource::Request))->toBe('HTTP request');
});

it('finds parent and child executions for an installation', function (): void {
    $parent = createDeckExecution([
        'job_class' => 'App\\Jobs\\ParentJob',
    ]);

    $execution = createDeckExecution([
        'job_class' => 'App\\Jobs\\ChildJob',
        'parent_job_uuid' => $parent->uuid,
        'parent_job_class' => $parent->job_class,
        'dispatch_group_id' => 'req-group',
    ]);

    $child = createDeckExecution([
        'job_class' => 'App\\Jobs\\GrandchildJob',
        'parent_job_uuid' => $execution->uuid,
        'parent_job_class' => $execution->job_class,
    ]);

    $sibling = createDeckExecution([
        'job_class' => 'App\\Jobs\\SiblingJob',
        'dispatch_group_id' => 'req-group',
    ]);

    expect(ExecutionObservability::parentExecution($execution)?->uuid)->toBe($parent->uuid)
        ->and(ExecutionObservability::childExecutionsQuery($execution)->pluck('uuid')->all())->toBe([(string) $child->uuid])
        ->and(ExecutionObservability::relatedGroupQuery($execution)->pluck('uuid')->all())->toBe([(string) $sibling->uuid]);
});
