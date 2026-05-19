<?php

namespace Deck\Deck\Support;

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Facades\Artisan;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob;
use ReflectionClass;
use ReflectionException;

class JobExecutionRetry
{
    public function retry(JobExecution $execution): RetryExecutionResult
    {
        if ($execution->status !== JobExecutionStatus::Failed) {
            return new RetryExecutionResult(
                success: false,
                message: 'Only failed executions can be retried.',
            );
        }

        if ($result = $this->retryViaHorizon($execution)) {
            return $result;
        }

        if ($result = $this->retryViaQueueFailer($execution)) {
            return $result;
        }

        return $this->retryByRedispatch($execution);
    }

    protected function retryViaHorizon(JobExecution $execution): ?RetryExecutionResult
    {
        if (! DeckHorizon::isInstalled()) {
            return null;
        }

        $failed = app(JobRepository::class)->findFailed($execution->uuid);

        if ($failed === null) {
            return null;
        }

        dispatch(new RetryFailedJob($execution->uuid));

        return new RetryExecutionResult(
            success: true,
            message: 'Job has been queued for retry using the original Horizon payload.',
        );
    }

    protected function retryViaQueueFailer(JobExecution $execution): ?RetryExecutionResult
    {
        if (! app()->bound('queue.failer')) {
            return null;
        }

        $failer = app('queue.failer');

        try {
            $failed = $failer->find($execution->uuid);
        } catch (\Throwable) {
            return null;
        }

        if ($failed === null) {
            return null;
        }

        Artisan::call('queue:retry', ['id' => [$execution->uuid]]);

        return new RetryExecutionResult(
            success: true,
            message: 'Job has been queued for retry using the stored failed-job payload.',
        );
    }

    protected function retryByRedispatch(JobExecution $execution): RetryExecutionResult
    {
        $jobClass = $execution->job_class;

        if (! class_exists($jobClass)) {
            return new RetryExecutionResult(
                success: false,
                message: "Job class [{$jobClass}] does not exist.",
            );
        }

        try {
            $reflection = new ReflectionClass($jobClass);
        } catch (ReflectionException) {
            return new RetryExecutionResult(
                success: false,
                message: 'Unable to inspect the job class for retry.',
            );
        }

        if (! $reflection->implementsInterface(ShouldQueue::class)) {
            return new RetryExecutionResult(
                success: false,
                message: 'Job class does not implement ShouldQueue.',
            );
        }

        if (! $reflection->isInstantiable()) {
            return new RetryExecutionResult(
                success: false,
                message: 'Job class cannot be instantiated without the original failed-job payload.',
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            return new RetryExecutionResult(
                success: false,
                message: 'Job requires constructor arguments. Use Horizon or the database failed_jobs driver so the original payload can be retried.',
            );
        }

        /** @var ShouldQueue $job */
        $job = $reflection->newInstance();

        $pending = dispatch($job);

        if ($pending instanceof PendingDispatch) {
            if ($execution->connection !== '') {
                $pending->onConnection($execution->connection);
            }

            if ($execution->queue !== '') {
                $pending->onQueue($execution->queue);
            }
        }

        return new RetryExecutionResult(
            success: true,
            message: 'A new job instance was dispatched without the original payload. Verify constructor dependencies if the job needs them.',
        );
    }
}
