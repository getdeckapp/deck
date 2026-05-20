<?php

use Deck\Deck\Support\DeferJobLifecycleRecording;

it('runs callbacks immediately during tests', function () {
    $ran = false;

    DeferJobLifecycleRecording::run(function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});
