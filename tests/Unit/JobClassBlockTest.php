<?php

use TorMorten\Deck\Support\JobClassBlock;

it('blocks and unblocks a job class', function () {
    $class = 'App\\Jobs\\BlockedJob';

    JobClassBlock::block($class);

    expect(JobClassBlock::isBlocked($class))->toBeTrue();

    JobClassBlock::unblock($class);

    expect(JobClassBlock::isBlocked($class))->toBeFalse();
});

it('blocks a job class until a timestamp', function () {
    $class = 'App\\Jobs\\TimedBlockJob';
    $until = now()->addHour();

    JobClassBlock::block($class, $until);

    expect(JobClassBlock::isBlocked($class))->toBeTrue()
        ->and(JobClassBlock::blockedUntil($class)?->toIso8601String())->toBe($until->toIso8601String())
        ->and(JobClassBlock::isManualBlock($class))->toBeFalse();
});

it('treats manual blocks as blocked without an until time', function () {
    $class = 'App\\Jobs\\ManualBlockJob';

    JobClassBlock::block($class);

    expect(JobClassBlock::isBlocked($class))->toBeTrue()
        ->and(JobClassBlock::isManualBlock($class))->toBeTrue()
        ->and(JobClassBlock::blockedUntil($class))->toBeNull();
});

it('clears expired timed blocks', function () {
    $class = 'App\\Jobs\\ExpiredBlockJob';

    JobClassBlock::block($class, now()->addSeconds(30));

    $this->travel(31)->seconds();

    expect(JobClassBlock::isBlocked($class))->toBeFalse();
});

it('stores an optional block reason for auditing', function () {
    $class = 'App\\Jobs\\AuditedBlockJob';

    JobClassBlock::block($class, now()->addHour(), 'Rolling back a bad deploy');

    $audit = JobClassBlock::audit($class);

    expect($audit)->not->toBeNull()
        ->and($audit->reason)->toBe('Rolling back a bad deploy')
        ->and($audit->blockedAt)->not->toBeNull();
});

it('clears block audit metadata when unblocking', function () {
    $class = 'App\\Jobs\\ClearAuditJob';

    JobClassBlock::block($class, reason: 'Maintenance window');
    JobClassBlock::unblock($class);

    expect(JobClassBlock::audit($class))->toBeNull();
});

it('truncates overly long block reasons', function () {
    $class = 'App\\Jobs\\LongReasonJob';

    config()->set('deck.block_reason_max_length', 20);

    JobClassBlock::block($class, reason: str_repeat('a', 40));

    expect(JobClassBlock::audit($class)?->reason)->toHaveLength(20);
});
