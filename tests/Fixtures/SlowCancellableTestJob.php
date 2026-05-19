<?php

namespace Deck\Deck\Tests\Fixtures;

use Deck\Deck\Middleware\Cancellable;
use Deck\Deck\Support\JobCancellation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SlowCancellableTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function middleware(): array
    {
        return [new Cancellable];
    }

    public function handle(): void
    {
        for ($i = 0; $i < 5; $i++) {
            JobCancellation::throwIfCancelled($this->job);
            usleep(50_000);
        }
    }
}
