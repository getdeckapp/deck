<?php

namespace TorMorten\Deck\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laravel\Horizon\Horizon;

class InstallCommand extends Command
{
    protected $signature = 'deck:install {--force : Overwrite existing published files}';

    protected $description = 'Publish Deck configuration, migrations, and assets';

    public function handle(): int
    {
        $this->components->info('Publishing Deck configuration...');

        $this->call('vendor:publish', [
            '--tag' => 'deck-config',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'deck-migrations',
            '--force' => $this->option('force'),
        ]);

        $this->components->info('Publishing Deck assets...');

        $this->call('vendor:publish', [
            '--tag' => 'deck-assets',
            '--force' => $this->option('force'),
        ]);

        $this->components->info('Run `php artisan migrate` to create Deck tables.');

        $this->components->info('Opt-in cooperative cancellation: add `TorMorten\\Deck\\Middleware\\Cancellable` to job middleware, and call `JobCancellation::throwIfCancelled($this->job)` between long steps. Cancel via `Deck::cancel($uuid)` or the dashboard.');

        $this->configureHorizonMiddleware();

        return self::SUCCESS;
    }

    private function configureHorizonMiddleware(): void
    {
        if (! class_exists(Horizon::class)) {
            return;
        }

        $horizonConfig = config_path('horizon.php');

        if (! File::exists($horizonConfig)) {
            $this->components->warn('Publish config/horizon.php, then add to `middleware`:');
            $this->line('  \\TorMorten\\Deck\\Http\\Middleware\\PromptHorizonOrDeck::class,');

            return;
        }

        $middleware = '\\TorMorten\\Deck\\Http\\Middleware\\PromptHorizonOrDeck::class';

        if (str_contains(File::get($horizonConfig), 'PromptHorizonOrDeck')) {
            $this->components->info('Horizon prompt middleware is already configured.');

            return;
        }

        $this->components->warn('Add to the `middleware` array in config/horizon.php:');
        $this->line('  '.$middleware.',');
    }
}
