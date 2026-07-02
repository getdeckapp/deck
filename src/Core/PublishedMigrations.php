<?php

namespace Deck\Deck\Core;

class PublishedMigrations
{
    /**
     * Base names of the migrations Deck runs itself since 1.1.15. Copies published
     * by pre-1.1.15 installs still linger in the app's database/migrations and
     * re-run their un-guarded Schema::create() after the package's guarded
     * migrations already created the tables — producing "table already exists"
     * on upgrade.
     *
     * @var list<string>
     */
    private const DECK_MIGRATION_BASE_NAMES = [
        'create_deck_tables',
        'add_project_and_environment_to_deck_tables',
        'add_exception_trace_to_deck_job_executions',
        'add_created_at_index_to_deck_job_executions',
        'add_observability_v2_to_deck_job_executions',
    ];

    /**
     * Stale Deck migration file names published into the app's database/migrations.
     *
     * @return list<string>
     */
    public static function stale(): array
    {
        $migrationsPath = database_path('migrations');

        if (! is_dir($migrationsPath)) {
            return [];
        }

        $stale = [];

        foreach ((array) glob($migrationsPath.'/*.php') as $file) {
            // Strip the leading YYYY_MM_DD_HHMMSS_ timestamp and the .php suffix.
            $base = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename((string) $file, '.php'));

            if (in_array($base, self::DECK_MIGRATION_BASE_NAMES, true)) {
                $stale[] = basename((string) $file);
            }
        }

        return $stale;
    }
}
