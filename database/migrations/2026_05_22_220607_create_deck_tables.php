<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Deck\Deck\Core\DeckDatabase;

return new class extends Migration
{
    public function up(): void
    {
        $statsTable = config('deck.tables.job_class_stats', 'deck_job_class_stats');
        $executionsTable = config('deck.tables.job_executions', 'deck_job_executions');
        $schema = DeckDatabase::schema();

        if (! $schema->hasTable($statsTable)) {
            $schema->create($statsTable, function (Blueprint $table) {
                $table->id();
                $table->string('project');
                $table->string('environment');
                $table->string('job_class');
                $table->timestamp('last_started_at')->nullable();
                $table->timestamp('last_finished_at')->nullable();
                $table->string('last_status', 32)->nullable();
                $table->unsignedInteger('last_duration_ms')->nullable();
                $table->uuid('last_uuid')->nullable();
                $table->unsignedBigInteger('success_count')->default(0);
                $table->unsignedBigInteger('failure_count')->default(0);
                $table->timestamps();

                $table->unique(['project', 'environment', 'job_class']);
                $table->index(['project', 'environment', 'last_finished_at']);
            });
        }

        if ($schema->hasTable($executionsTable)) {
            return;
        }

        $schema->create($executionsTable, function (Blueprint $table) {
            $table->id();
            $table->string('project');
            $table->string('environment');
            $table->uuid('uuid');
            $table->string('job_class');
            $table->string('connection');
            $table->string('queue');
            $table->string('status', 32);
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->json('tags')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->text('exception_trace')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['uuid', 'attempt']);
            $table->index(['project', 'environment', 'job_class', 'started_at'], 'deck_exec_proj_env_class_started_idx');
            $table->index(['project', 'environment', 'status', 'started_at'], 'deck_exec_proj_env_status_started_idx');
            // Supports deck:prune retention deletes without scanning the full executions table.
            $table->index('created_at', 'deck_exec_created_at_idx');
        });
    }

    public function down(): void
    {
        DeckDatabase::schema()->dropIfExists(config('deck.tables.job_executions', 'deck_job_executions'));
        DeckDatabase::schema()->dropIfExists(config('deck.tables.job_class_stats', 'deck_job_class_stats'));
    }
};
