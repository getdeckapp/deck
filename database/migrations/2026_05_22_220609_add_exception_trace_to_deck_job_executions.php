<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Deck\Deck\Core\DeckDatabase;

return new class extends Migration
{
    public function up(): void
    {
        $executionsTable = config('deck.tables.job_executions', 'deck_job_executions');
        $schema = DeckDatabase::schema();

        if (! $schema->hasTable($executionsTable)) {
            return;
        }

        if ($schema->hasColumn($executionsTable, 'exception_trace')) {
            return;
        }

        $schema->table($executionsTable, function (Blueprint $table) {
            $table->text('exception_trace')->nullable()->after('exception_message');
        });
    }

    public function down(): void
    {
        $executionsTable = config('deck.tables.job_executions', 'deck_job_executions');
        $schema = DeckDatabase::schema();

        if (! $schema->hasColumn($executionsTable, 'exception_trace')) {
            return;
        }

        $schema->table($executionsTable, function (Blueprint $table) {
            $table->dropColumn('exception_trace');
        });
    }
};
