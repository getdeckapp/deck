<?php

use Deck\Deck\Recording\JobExecutionTiming;
use Illuminate\Support\Carbon;

it('remembers and peeks running state from cache without consuming it', function () {
    $uuid = (string) str()->uuid();
    $startedAt = Carbon::parse('2026-05-20 10:00:00');

    JobExecutionTiming::remember($uuid, 1, $startedAt, 1_500);

    $state = JobExecutionTiming::peek($uuid, 1);

    expect($state)->not->toBeNull()
        ->and($state->isRunning())->toBeTrue()
        ->and($state->startedAt?->toIso8601String())->toBe($startedAt->toIso8601String())
        ->and($state->waitMs)->toBe(1_500)
        // peek does not consume: a second peek still resolves.
        ->and(JobExecutionTiming::peek($uuid, 1)?->isRunning())->toBeTrue();
});

it('transitions running state to terminal', function () {
    $uuid = (string) str()->uuid();

    JobExecutionTiming::remember($uuid, 1, Carbon::parse('2026-05-20 10:00:00'));
    JobExecutionTiming::markTerminal($uuid, 1);

    expect(JobExecutionTiming::peek($uuid, 1)?->isTerminal())->toBeTrue();
});

it('marks blocked state', function () {
    $uuid = (string) str()->uuid();

    JobExecutionTiming::markBlocked($uuid, 1);

    expect(JobExecutionTiming::peek($uuid, 1)?->isBlocked())->toBeTrue();
});

it('forgets state', function () {
    $uuid = (string) str()->uuid();

    JobExecutionTiming::remember($uuid, 1, Carbon::parse('2026-05-20 10:00:00'));
    JobExecutionTiming::forget($uuid, 1);

    expect(JobExecutionTiming::peek($uuid, 1))->toBeNull();
});
