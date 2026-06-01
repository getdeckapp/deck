<?php

use Deck\Deck\Cloud\DeckCloud;

it('is disabled when no api key is configured', function () {
    config()->set('deck.cloud.api_key', null);
    config()->set('deck.cloud.enabled', null);

    expect(DeckCloud::isEnabled())->toBeFalse();
});

it('enables cloud when only an api key is configured', function () {
    config()->set('deck.cloud.api_key', 'agent-token');
    config()->set('deck.cloud.url', null);
    config()->set('deck.cloud.enabled', null);
    config()->set('app.env', 'local');

    expect(DeckCloud::isEnabled())->toBeTrue()
        ->and(DeckCloud::resolvedUrl())->toBe('http://deck.test')
        ->and(DeckCloud::workersEnabled())->toBeTrue()
        ->and(DeckCloud::commandsEnabled())->toBeTrue();
});

it('auto-enables cloud when enabled config is null', function () {
    config()->set('deck.cloud.api_key', 'agent-token');
    config()->set('deck.cloud.enabled', null);

    expect(DeckCloud::isEnabled())->toBeTrue();
});

it('defaults cloud url to deckapp.cloud outside local', function () {
    config()->set('app.env', 'production');

    expect(DeckCloud::defaultUrl())->toBe('https://app.deckapp.cloud');
});

it('can be explicitly disabled while keeping an api key', function () {
    config()->set('deck.cloud.api_key', 'agent-token');
    config()->set('deck.cloud.url', 'https://cloud.example.test');
    config()->set('deck.cloud.enabled', false);

    expect(DeckCloud::isEnabled())->toBeFalse();
});

it('respects an explicit cloud url override', function () {
    config()->set('deck.cloud.api_key', 'agent-token');
    config()->set('deck.cloud.url', 'https://cloud.example.test');
    config()->set('app.env', 'local');

    expect(DeckCloud::resolvedUrl())->toBe('https://cloud.example.test');
});
