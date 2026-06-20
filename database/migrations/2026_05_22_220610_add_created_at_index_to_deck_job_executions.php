<?php

use Deck\Deck\Core\DeckDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $executionsTable = config('deck.tables.job_executions', 'deck_job_executions');
        $schema = DeckDatabase::schema();

        if (! $schema->hasTable($executionsTable)) {
            return;
        }

        $indexName = 'deck_exec_created_at_idx';

        if ($schema->hasIndex($executionsTable, $indexName)) {
            return;
        }

        $schema->table($executionsTable, function (Blueprint $table) use ($indexName): void {
            $table->index('created_at', $indexName);
        });
    }

    public function down(): void
    {
        $executionsTable = config('deck.tables.job_executions', 'deck_job_executions');
        $schema = DeckDatabase::schema();

        if (! $schema->hasTable($executionsTable)) {
            return;
        }

        if (! $schema->hasIndex($executionsTable, 'deck_exec_created_at_idx')) {
            return;
        }

        $schema->table($executionsTable, function (Blueprint $table): void {
            $table->dropIndex('deck_exec_created_at_idx');
        });
    }
};
