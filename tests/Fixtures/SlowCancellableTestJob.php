<?php

namespace TorMorten\Deck\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use TorMorten\Deck\Middleware\Cancellable;
use TorMorten\Deck\Support\JobCancellation;

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
