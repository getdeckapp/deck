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
