<?php

use Deck\Deck\Cloud\Agent\SyncThrottle;

it('throttles cloud worker reports per channel and key', function () {
    config()->set('deck.cloud.workers.interval_seconds', 30);

    $throttle = new SyncThrottle;

    expect($throttle->shouldSync('workers', 'host'))->toBeTrue()
        ->and($throttle->shouldSync('workers', 'host'))->toBeFalse()
        ->and($throttle->shouldSync('workers', 'redis:default'))->toBeTrue();
});
