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

        $this->components->info('Job blocking is enabled automatically: blocked jobs are released back to the queue with a delay. Use `Deck::blockClass()` / the job detail UI.');

        $this->components->info('Optional alerts: set DECK_ALERTS_ENABLED=true, configure deck.alerts.stale_jobs, and schedule `php artisan deck:check-alerts` hourly in routes/console.php.');

        $this->configureHorizonMiddleware();

        return self::SUCCESS;
    }

    private function configureHorizonMiddleware(): void
    {
        if (! class_exists(Horizon::class)) {
            return;
        }

        $horizonConfig = config_path('horizon.php');

        $middleware = [
            '\\TorMorten\\Deck\\Http\\Middleware\\PromptHorizonOrDeck::class',
            '\\TorMorten\\Deck\\Http\\Middleware\\InjectHorizonDeckBanner::class',
        ];

        if (! File::exists($horizonConfig)) {
            $this->components->warn('Publish config/horizon.php, then add to `middleware`:');
            foreach ($middleware as $class) {
                $this->line('  '.$class.',');
            }

            return;
        }

        $contents = File::get($horizonConfig);
        $missing = array_filter($middleware, fn (string $class): bool => ! str_contains($contents, class_basename($class)));

        if ($missing === []) {
            $this->components->info('Horizon Deck middleware is already configured.');

            return;
        }

        $this->components->warn('Add to the `middleware` array in config/horizon.php:');
        foreach ($missing as $class) {
            $this->line('  '.$class.',');
        }
    }
}
