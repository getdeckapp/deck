<?php

use Deck\Deck\Queue\DeckCallQueuedHandler;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;

class UniqueUntilProcessingListenerFixture implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    public function handle(): void {}
}

class PlainListenerFixture implements ShouldQueue
{
    public function handle(): void {}
}

/**
 * Exposes the protected helper so we can assert it never calls a method that
 * older Laravel releases do not define on CallQueuedListener.
 */
class ExposedDeckCallQueuedHandler extends DeckCallQueuedHandler
{
    public function callCommandShouldBeUniqueUntilProcessing(mixed $command): bool
    {
        return $this->commandShouldBeUniqueUntilProcessing($command);
    }
}

function makeExposedDeckHandler(): ExposedDeckCallQueuedHandler
{
    return new ExposedDeckCallQueuedHandler(
        app(BusDispatcher::class),
        app(),
    );
}

it('detects unique-until-processing queued listeners without calling a listener method', function () {
    $command = new CallQueuedListener(UniqueUntilProcessingListenerFixture::class, 'handle', []);

    expect(makeExposedDeckHandler()->callCommandShouldBeUniqueUntilProcessing($command))->toBeTrue();
});

it('returns false for queued listeners that are not unique-until-processing', function () {
    $command = new CallQueuedListener(PlainListenerFixture::class, 'handle', []);

    expect(makeExposedDeckHandler()->callCommandShouldBeUniqueUntilProcessing($command))->toBeFalse();
});
