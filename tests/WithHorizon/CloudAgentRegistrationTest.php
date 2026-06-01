<?php

namespace Deck\Deck\Tests\WithHorizon;

use Deck\Deck\Horizon\DeckHorizon;
use Deck\Deck\Tests\Support\HorizonInstalledTestCase;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class CloudAgentRegistrationTest extends HorizonInstalledTestCase
{
    #[Test]
    public function it_does_not_sync_deck_cloud_on_queue_loop_when_horizon_is_installed(): void
    {
        $this->assertTrue(DeckHorizon::isInstalled());

        enableDeckCloudForTests();

        Http::fake([
            'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 1], 202),
            'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response(['commands' => []]),
        ]);

        event(new Looping('redis', 'default'));

        Http::assertNothingSent();
    }
}
