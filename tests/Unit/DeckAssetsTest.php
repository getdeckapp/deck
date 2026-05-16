<?php

use TorMorten\Deck\Support\DeckAssets;

it('returns a stylesheet link tag from styles', function () {
    $html = DeckAssets::styles();

    expect($html)
        ->toContain('<link rel="stylesheet"')
        ->toContain('deck.css');
});

it('throws when the dist file is missing', function () {
    DeckAssets::url('missing.css');
})->throws(RuntimeException::class);

it('resolves the package dist path', function () {
    $path = DeckAssets::packageDistPath('deck.css');

    expect($path)->toEndWith('resources/dist/deck.css')
        ->and(is_file($path))->toBeTrue();
});
