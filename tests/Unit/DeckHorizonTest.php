<?php

use Deck\Deck\Horizon\DeckHorizon;

it('reports horizon as not installed when horizon is not bootstrapped', function () {
    expect(DeckHorizon::isInstalled())->toBeFalse();
});

it('returns null dashboard url when horizon is not installed', function () {
    expect(DeckHorizon::dashboardUrl())->toBeNull();
});
