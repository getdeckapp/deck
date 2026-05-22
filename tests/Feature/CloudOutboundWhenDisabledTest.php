<?php

use Deck\Deck\Cloud\Agent\AgentSync;
use Deck\Deck\Cloud\Commands\CommandApplicator;
use Deck\Deck\Cloud\Commands\CommandPoller;
use Deck\Deck\Cloud\Connection\HttpClient;
use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Cloud\Workers\WorkerReporter;
use Deck\Deck\Cloud\Workers\WorkerSnapshot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('deck.cloud.enabled', false);
    config()->set('deck.cloud.url', 'https://cloud.deck.test');
    config()->set('deck.cloud.api_key', 'leaked-key');
    config()->set('deck.cloud.workers.enabled', true);
    config()->set('deck.cloud.commands.enabled', true);
});

it('does not register cloud agent services when cloud is disabled', function () {
    expect(DeckCloud::isEnabled())->toBeFalse()
        ->and(app()->bound(AgentSync::class))->toBeFalse()
        ->and(app()->bound(HttpClient::class))->toBeFalse();
});

it('does not open outbound http from the cloud http client when disabled', function () {
    Http::fake();

    $client = new HttpClient;

    expect($client->get('/api/v1/agent/commands'))->toBeNull()
        ->and($client->post('/api/v1/ingest/workers', ['workers' => []]))->toBeFalse();

    Http::assertNothingSent();
});

it('does not open outbound http from worker reporting when disabled', function () {
    Http::fake();

    $reporter = new WorkerReporter(new HttpClient);

    $reporter->send([
        new WorkerSnapshot(
            supervisor: 'supervisor-1',
            name: 'redis:default',
            connection: 'redis',
            queue: 'default',
            status: 'running',
            processes: 1,
        ),
    ]);

    Http::assertNothingSent();
});

it('does not open outbound http from command polling when disabled', function () {
    Http::fake();

    $poller = new CommandPoller(
        new HttpClient,
        Mockery::mock(CommandApplicator::class),
    );

    $poller->poll();

    Http::assertNothingSent();
});

it('does not phone home when agent sync runs while cloud is disabled', function () {
    Http::fake();

    app(AgentSync::class)->syncQueueWorker('redis', 'default');

    Http::assertNothingSent();
});

it('does not open outbound http when running deck report workers with cloud disabled', function () {
    Http::fake();

    expect(Artisan::call('deck:report-workers'))->toBe(0);

    Http::assertNothingSent();
});

it('registers cloud agent services when cloud is enabled for tests', function () {
    enableDeckCloudForTests();

    expect(app()->bound(AgentSync::class))->toBeTrue()
        ->and(app()->bound(HttpClient::class))->toBeTrue()
        ->and(app()->bound(CommandPoller::class))->toBeTrue();
});

it('does not report workers when worker sync is disabled', function () {
    enableDeckCloudForTests();
    config()->set('deck.cloud.workers.enabled', false);

    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 1], 202),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
        'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response(['commands' => []]),
    ]);

    app(AgentSync::class)->syncQueueWorker('redis', 'default');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/ingest/workers'));
});

it('does not open outbound http when cloud url or api key is missing even if enabled flag is true', function () {
    Http::fake();

    config()->set('deck.cloud.enabled', true);
    config()->set('deck.cloud.api_key', null);

    expect(DeckCloud::isEnabled())->toBeFalse();

    (new HttpClient)->post('/api/v1/ingest/workers', ['workers' => [['project' => 'x']]]);

    Http::assertNothingSent();
});
