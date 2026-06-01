<?php

use Deck\Deck\Cancellation\JobCancellation;
use Deck\Deck\Cancellation\PendingJobCancellation;

it('sets the cancellation flag for pending cancel', function () {
    $uuid = (string) str()->uuid();

    $result = PendingJobCancellation::cancel($uuid, 'sync');

    expect($result->cancelFlagSet)->toBeTrue()
        ->and(JobCancellation::isCancelled($uuid))->toBeTrue()
        ->and($result->removedFromQueue)->toBeFalse();
});
