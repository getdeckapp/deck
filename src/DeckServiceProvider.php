<?php

namespace TorMorten\Deck;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use TorMorten\Deck\Commands\CheckAlertsCommand;
use TorMorten\Deck\Commands\InstallCommand;
use TorMorten\Deck\Commands\PruneCommand;
use TorMorten\Deck\Contracts\JobExecutionRecorder;
use TorMorten\Deck\Listeners\RecordJobExecution;
use TorMorten\Deck\Livewire\Dashboard;
use TorMorten\Deck\Livewire\JobClassIndex;
use TorMorten\Deck\Livewire\JobClassShow;
use TorMorten\Deck\Livewire\JobExecutionIndex;
use TorMorten\Deck\Livewire\JobExecutionShow;
use TorMorten\Deck\Livewire\WorkersIndex;
use TorMorten\Deck\Recorders\DatabaseJobExecutionRecorder;
use TorMorten\Deck\Support\HorizonSnapshot;

class DeckServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('deck')
            ->hasConfigFile()
            ->hasViews()
            ->hasAssets()
            ->hasRoutes('web')
            ->hasMigration('create_deck_tables')
            ->hasMigration('add_project_and_environment_to_deck_tables')
            ->hasMigration('add_exception_trace_to_deck_job_executions')
            ->hasCommand(InstallCommand::class)
            ->hasCommand(PruneCommand::class)
            ->hasCommand(CheckAlertsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(JobExecutionRecorder::class, DatabaseJobExecutionRecorder::class);
        $this->app->singleton(Deck::class);
        $this->app->singleton(HorizonSnapshot::class, fn (): HorizonSnapshot => HorizonSnapshot::make());
    }

    public function packageBooted(): void
    {
        $listener = $this->app->make(RecordJobExecution::class);

        Event::listen(JobProcessing::class, [$listener, 'handleProcessing']);
        Event::listen(JobProcessed::class, [$listener, 'handleProcessed']);
        Event::listen(JobFailed::class, [$listener, 'handleFailed']);

        if (class_exists(Livewire::class)) {
            Livewire::component('deck.dashboard', Dashboard::class);
            Livewire::component('deck.job-class-index', JobClassIndex::class);
            Livewire::component('deck.job-class-show', JobClassShow::class);
            Livewire::component('deck.job-execution-index', JobExecutionIndex::class);
            Livewire::component('deck.job-execution-show', JobExecutionShow::class);
            Livewire::component('deck.workers-index', WorkersIndex::class);
        }
    }
}
