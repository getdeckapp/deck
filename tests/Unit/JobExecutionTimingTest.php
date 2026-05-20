<?php

use Deck\Deck\Support\JobExecutionTiming;
use Illuminate\Support\Carbon;

it('remembers and resolves execution start times from cache', function () {
    $uuid = (string) str()->uuid();
    $startedAt = Carbon::parse('2026-05-20 10:00:00');

    JobExecutionTiming::remember($uuid, 1, $startedAt);

    expect(JobExecutionTiming::resolve($uuid, 1)?->toIso8601String())
        ->toBe($startedAt->toIso8601String())
        ->and(JobExecutionTiming::resolve($uuid, 1))->toBeNull();
});
