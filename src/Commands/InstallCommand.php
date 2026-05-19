<?php

namespace Deck\Deck\Commands;

use Deck\Deck\Support\DeckAssets;
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

        if (! is_file(DeckAssets::packageDistPath('deck.css'))) {
            $this->components->warn('Precompiled CSS is missing. From the Deck package directory, run `npm install && npm run build`, then run `deck:install --force` again.');
        } else {
            $this->components->info('After changing Deck views or styles, run `npm run build` in the package and `deck:install --force` to refresh `public/vendor/deck/deck.css`.');
        }

        $connection = config('deck.database_connection');

        if (filled($connection)) {
            $this->components->info("Run `php artisan migrate --database={$connection}` to create Deck tables on the `{$connection}` connection.");
        } else {
            $this->components->info('Run `php artisan migrate` to create Deck tables.');
            $this->components->info('To offload Deck to a separate database, set DECK_DB_CONNECTION and add the connection in config/database.php.');
        }

        $this->components->info('Opt-in cooperative cancellation: add `Deck\\Deck\\Middleware\\Cancellable` to job middleware, and call `JobCancellation::throwIfCancelled($this->job)` between long steps. Cancel via `Deck::cancel($uuid)` or the dashboard.');

        $this->components->info('Job blocking is enabled automatically: blocked dispatches are recorded in Deck and never pushed to the queue. Use `Deck::blockClass()` / the job detail UI.');

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
            '\\Deck\\Deck\\Http\\Middleware\\PromptHorizonOrDeck::class',
            '\\Deck\\Deck\\Http\\Middleware\\InjectHorizonDeckBanner::class',
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
