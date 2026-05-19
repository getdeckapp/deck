<?php

use Deck\Deck\Support\DeckHorizon;

it('reports horizon as not installed in the test harness', function () {
    expect(DeckHorizon::isInstalled())->toBeFalse();
});

it('returns null dashboard url when horizon is not installed', function () {
    expect(DeckHorizon::dashboardUrl())->toBeNull();
});
