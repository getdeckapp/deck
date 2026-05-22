<?php

use Deck\Deck\Support\DeckHorizon;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Http;

it('syncs deck cloud on queue loop when horizon is not installed', function () {
    expect(DeckHorizon::isInstalled())->toBeFalse();

    enableDeckCloudForTests();
    resetDeckCloudSyncThrottle();

    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 1], 202),
        'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response(['commands' => []]),
    ]);

    event(new Looping('redis', 'default'));

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/api/v1/ingest/workers'));
});
