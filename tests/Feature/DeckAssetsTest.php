<?php

use Deck\Deck\Presentation\DeckAssets;

it('serves precompiled css from the package', function () {
    $path = DeckAssets::packageDistPath('deck.css');

    expect(is_file($path))->toBeTrue();

    $response = $this->get(route('deck.assets', ['file' => 'deck.css']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toStartWith('text/css');
});

it('resolves stylesheet url from package dist when not published', function () {
    $published = public_path('vendor/deck/deck.css');

    if (is_file($published)) {
        unlink($published);
    }

    $url = DeckAssets::url('deck.css');

    expect($url)->toContain('deck/assets/deck.css');
});

it('prefers published assets when present', function () {
    $published = public_path('vendor/deck/deck.css');

    if (! is_dir(dirname($published))) {
        mkdir(dirname($published), 0755, true);
    }

    copy(DeckAssets::packageDistPath('deck.css'), $published);

    $url = DeckAssets::url('deck.css');

    expect($url)->toContain('vendor/deck/deck.css');
});

it('returns 404 for invalid asset files', function () {
    $this->get(route('deck.assets', ['file' => 'evil.css']))->assertNotFound();
    $this->get(route('deck.assets', ['file' => '../deck.css']))->assertNotFound();
});
