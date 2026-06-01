<?php

use Deck\Deck\Core\DeferDeckSideEffects;

it('does not defer side effects in tests or the console', function () {
    config()->set('deck.defer_side_effects', true);

    expect(DeferDeckSideEffects::shouldDefer())->toBeFalse();
});

it('does not defer side effects when disabled in config', function () {
    config()->set('deck.defer_side_effects', false);

    expect(DeferDeckSideEffects::shouldDefer())->toBeFalse();
});

it('runs callbacks immediately when not deferring', function () {
    config()->set('deck.defer_side_effects', false);

    $ran = false;

    DeferDeckSideEffects::run(function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});
