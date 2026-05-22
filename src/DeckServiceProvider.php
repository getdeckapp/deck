<?php

namespace Deck\Deck;

use Deck\Deck\Bus\DeckDispatcher;
use Deck\Deck\Cloud\CloudEventBuffer;
use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Commands\CheckAlertsCommand;
use Deck\Deck\Commands\CloudBackfillCommand;
use Deck\Deck\Commands\DoctorCommand;
use Deck\Deck\Commands\InstallCommand;
use Deck\Deck\Commands\PollCommandsCommand;
use Deck\Deck\Commands\PruneCommand;
use Deck\Deck\Commands\ReportWorkersCommand;
use Deck\Deck\Concerns\RegistersCloudAgent;
use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Listeners\FlushDeckCloudEvents;
use Deck\Deck\Listeners\RecordJobExecution;
use Deck\Deck\Listeners\RecordJobProcessingListener;
use Deck\Deck\Livewire\Dashboard;
use Deck\Deck\Livewire\GlobalSearch;
use Deck\Deck\Livewire\JobClassIndex;
use Deck\Deck\Livewire\JobClassShow;
use Deck\Deck\Livewire\JobExecutionIndex;
use Deck\Deck\Livewire\JobExecutionShow;
use Deck\Deck\Livewire\WorkersIndex;
use Deck\Deck\Queue\DeckCallQueuedHandler;
use Deck\Deck\Recorders\CompositeJobExecutionRecorder;
use Deck\Deck\Recorders\DatabaseJobExecutionRecorder;
use Deck\Deck\Recorders\HttpJobExecutionRecorder;
use Deck\Deck\Support\HorizonSnapshot;
use Illuminate\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DeckServiceProvider extends PackageServiceProvider
{
    use RegistersCloudAgent;

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
            ->hasMigration('add_created_at_index_to_deck_job_executions')
            ->hasCommand(InstallCommand::class)
            ->hasCommand(PruneCommand::class)
            ->hasCommand(CheckAlertsCommand::class)
            ->hasCommand(PollCommandsCommand::class)
            ->hasCommand(ReportWorkersCommand::class)
            ->hasCommand(CloudBackfillCommand::class)
            ->hasCommand(DoctorCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DatabaseJobExecutionRecorder::class);
        $this->app->singleton(CloudEventBuffer::class);
        $this->app->singleton(HttpJobExecutionRecorder::class);
        $this->app->singleton(CompositeJobExecutionRecorder::class);
        $this->app->singleton(JobExecutionRecorder::class, function ($app): JobExecutionRecorder {
            if (DeckCloud::eventsEnabled()) {
                return $app->make(CompositeJobExecutionRecorder::class);
            }

            return $app->make(DatabaseJobExecutionRecorder::class);
        });
        $this->app->singleton(Deck::class);
        $this->app->singleton(HorizonSnapshot::class, fn (): HorizonSnapshot => HorizonSnapshot::make());

        $this->registerCloudAgent();
        $this->registerDeckCallQueuedHandler();
    }

    public function packageBooted(): void
    {
        $this->registerDeckDispatcher();
        $this->registerQueueListeners();
        $this->bootCloudAgent();
        $this->scheduleCloudAgent();

        if (class_exists(Livewire::class)) {
            Livewire::component('deck.dashboard', Dashboard::class);
            Livewire::component('deck.global-search', GlobalSearch::class);
            Livewire::component('deck.job-class-index', JobClassIndex::class);
            Livewire::component('deck.job-class-show', JobClassShow::class);
            Livewire::component('deck.job-execution-index', JobExecutionIndex::class);
            Livewire::component('deck.job-execution-show', JobExecutionShow::class);
            Livewire::component('deck.workers-index', WorkersIndex::class);
        }
    }

    private function registerDeckDispatcher(): void
    {
        $this->app->extend(BusDispatcher::class, function ($dispatcher, $app): DeckDispatcher {
            if ($dispatcher instanceof DeckDispatcher) {
                return $dispatcher;
            }

            return new DeckDispatcher($app, function (?string $connection = null) use ($app) {
                return $app->make(QueueFactoryContract::class)->connection($connection);
            });
        });
    }

    private function registerDeckCallQueuedHandler(): void
    {
        $factory = function ($app): DeckCallQueuedHandler {
            return new DeckCallQueuedHandler(
                $app->make(Dispatcher::class),
                $app,
            );
        };

        $this->app->singleton(DeckCallQueuedHandler::class, $factory);
        $this->app->singleton(CallQueuedHandler::class, fn ($app) => $app->make(DeckCallQueuedHandler::class));
    }

    private function registerQueueListeners(): void
    {
        $recorder = RecordJobExecution::class;

        Queue::before([RecordJobProcessingListener::class, 'handle']);
        Queue::after([$recorder, 'handleProcessed']);
        Queue::failing([$recorder, 'handleFailed']);

        Event::listen(JobAttempted::class, [$recorder, 'handleJobAttempted']);

        if (DeckCloud::eventsEnabled()) {
            Event::listen(JobAttempted::class, FlushDeckCloudEvents::class);
        }
    }
}
