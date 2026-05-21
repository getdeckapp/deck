<?php

use Deck\Deck\Cloud\CloudConnectionProbe;
use Deck\Deck\Cloud\CloudConnectionState;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('deck.cloud.enabled', true);
    config()->set('deck.cloud.url', 'https://cloud.deck.test');
    config()->set('deck.cloud.api_key', 'test-api-key');
    config()->set('deck.project', 'billing-api');
    config()->set('deck.environment', 'production');
    config()->set('deck.cloud.probe_cache_seconds', 60);

    app(CloudConnectionProbe::class)->forget();
});

it('reports disabled when cloud is not configured', function () {
    config()->set('deck.cloud.enabled', false);

    $status = app(CloudConnectionProbe::class)->status(fresh: true);

    expect($status->state)->toBe(CloudConnectionState::Disabled)
        ->and($status->isEnabled())->toBeFalse();
});

it('reports connected when the agent api responds successfully', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response(['commands' => []]),
    ]);

    $status = app(CloudConnectionProbe::class)->status(fresh: true);

    expect($status->state)->toBe(CloudConnectionState::Connected)
        ->and($status->label)->toBe('Connected')
        ->and($status->host)->toBe('cloud.deck.test')
        ->and($status->project)->toBe('billing-api');
});

it('reports unauthorized when the api key is rejected', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response([], 401),
    ]);

    $status = app(CloudConnectionProbe::class)->status(fresh: true);

    expect($status->state)->toBe(CloudConnectionState::Unauthorized);
});

it('caches connection status as an array to avoid incomplete class errors', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response(['commands' => []]),
    ]);

    $probe = app(CloudConnectionProbe::class);

    $probe->status(fresh: true);
    $cached = cache()->get('deck.cloud.connection:billing-api:production');

    expect($cached)->toBeArray()
        ->and($cached['state'])->toBe('connected');

    Http::fake();

    expect($probe->status()->state)->toBe(CloudConnectionState::Connected);
});

it('reports misconfigured when the installation is missing on cloud', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response(['message' => 'missing'], 404),
    ]);

    $status = app(CloudConnectionProbe::class)->status(fresh: true);

    expect($status->state)->toBe(CloudConnectionState::Misconfigured)
        ->and($status->detail)->toContain('billing-api');
});
