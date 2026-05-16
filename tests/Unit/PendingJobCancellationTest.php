<?php

use TorMorten\Deck\Support\JobCancellation;
use TorMorten\Deck\Support\PendingJobCancellation;

it('sets the cancellation flag for pending cancel', function () {
    $uuid = (string) str()->uuid();

    $result = PendingJobCancellation::cancel($uuid, 'sync');

    expect($result->cancelFlagSet)->toBeTrue()
        ->and(JobCancellation::isCancelled($uuid))->toBeTrue()
        ->and($result->removedFromQueue)->toBeFalse();
});
