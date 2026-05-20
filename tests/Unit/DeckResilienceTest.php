<?php

use Deck\Deck\Support\DeckResilience;

it('returns the default when a callback throws', function () {
    $result = DeckResilience::runSilently(
        fn () => throw new RuntimeException('redis unavailable'),
        'fallback',
    );

    expect($result)->toBe('fallback');
});

it('runs void callbacks without rethrowing', function () {
    DeckResilience::runSilentlyVoid(
        fn () => throw new RuntimeException('database unavailable'),
    );

    expect(true)->toBeTrue();
});
