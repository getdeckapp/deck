<?php

use TorMorten\Deck\Support\DeckAssets;

it('prefers the newer package dist css over a stale published copy', function () {
    $published = public_path('vendor/deck/deck.css');
    $package = DeckAssets::packageDistPath('deck.css');

    expect(is_file($package))->toBeTrue();

    if (! is_dir(dirname($published))) {
        mkdir(dirname($published), 0755, true);
    }

    copy($package, $published);
    touch($published, time() - 3600);
    touch($package, time());

    expect(DeckAssets::resolveAssetPath('deck.css'))->toBe($package);
});
