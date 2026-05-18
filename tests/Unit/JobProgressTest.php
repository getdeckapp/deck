<?php

use TorMorten\Deck\Support\JobProgress;

it('stores and retrieves job progress', function () {
    $uuid = (string) str()->uuid();

    JobProgress::update($uuid, 42, 'Processing invoices');

    $progress = JobProgress::get($uuid);

    expect($progress)->not->toBeNull()
        ->and($progress->percent)->toBe(42)
        ->and($progress->message)->toBe('Processing invoices');
});

it('clamps progress between zero and one hundred', function () {
    $uuid = (string) str()->uuid();

    JobProgress::update($uuid, 150);

    expect(JobProgress::get($uuid)?->percent)->toBe(100);

    JobProgress::update($uuid, -5);

    expect(JobProgress::get($uuid)?->percent)->toBe(0);
});

it('clears stored progress', function () {
    $uuid = (string) str()->uuid();

    JobProgress::update($uuid, 10);
    JobProgress::clear($uuid);

    expect(JobProgress::get($uuid))->toBeNull();
});
