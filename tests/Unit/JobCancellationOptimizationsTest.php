<?php

use Deck\Deck\Cancellation\JobCancellation;

it('consumes a cancellation flag in one step', function () {
    $uuid = (string) str()->uuid();

    JobCancellation::cancel($uuid);

    expect(JobCancellation::isCancelled($uuid))->toBeTrue()
        ->and(JobCancellation::consumeIfCancelled($uuid))->toBeTrue()
        ->and(JobCancellation::isCancelled($uuid))->toBeFalse()
        ->and(JobCancellation::consumeIfCancelled($uuid))->toBeFalse();
});

it('detects any cancelled uuid in a single cache batch', function () {
    $first = (string) str()->uuid();
    $second = (string) str()->uuid();

    JobCancellation::cancel($first);

    expect(JobCancellation::anyCancelled([$second]))->toBeFalse()
        ->and(JobCancellation::anyCancelled([$second, $first]))->toBeTrue();
});

it('does not throw when cache operations fail', function () {
    config()->set('deck.cancel_cache_store', 'missing-store');

    expect(JobCancellation::isCancelled('test-uuid'))->toBeFalse()
        ->and(JobCancellation::anyCancelled(['test-uuid']))->toBeFalse()
        ->and(JobCancellation::consumeIfCancelled('test-uuid'))->toBeFalse();
});
