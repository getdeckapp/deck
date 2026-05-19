<?php

use Deck\Deck\Cloud\AgentSync;
use Deck\Deck\Cloud\CommandApplicator;
use Deck\Deck\Cloud\CommandPoller;
use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Cloud\HttpClient;
use Deck\Deck\Cloud\WorkerReporter;
use Deck\Deck\Cloud\WorkerSnapshot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();

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
    $client = new HttpClient;

    expect($client->get('/api/v1/agent/commands'))->toBeNull()
        ->and($client->post('/api/v1/ingest/workers', ['workers' => []]))->toBeFalse();

    Http::assertNothingSent();
});

it('does not open outbound http from worker reporting when disabled', function () {
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
    $poller = new CommandPoller(
        new HttpClient,
        Mockery::mock(CommandApplicator::class),
    );

    $poller->poll();

    Http::assertNothingSent();
});

it('does not phone home when agent sync runs while cloud is disabled', function () {
    app(AgentSync::class)->syncQueueWorker('redis', 'default');

    Http::assertNothingSent();
});

it('does not open outbound http when running deck report workers with cloud disabled', function () {
    expect(Artisan::call('deck:report-workers'))->toBe(0);

    Http::assertNothingSent();
});

it('does not open outbound http when cloud url or api key is missing even if enabled flag is true', function () {
    config()->set('deck.cloud.enabled', true);
    config()->set('deck.cloud.api_key', null);

    expect(DeckCloud::isEnabled())->toBeFalse();

    (new HttpClient)->post('/api/v1/ingest/workers', ['workers' => [['project' => 'x']]]);

    Http::assertNothingSent();
});
