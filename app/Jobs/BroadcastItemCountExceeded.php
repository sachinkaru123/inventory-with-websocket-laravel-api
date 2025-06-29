<?php

namespace App\Jobs;

use App\Events\ItemCountExeed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable as BusQueueable;

class BroadcastItemCountExceeded implements ShouldQueue
{
    use BusQueueable, InteractsWithQueue, SerializesModels;

    public int $currentCount;
    public int $threshold;
    public string $severity;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(int $currentCount, int $threshold, string $severity = 'warning')
    {
        $this->currentCount = $currentCount;
        $this->threshold = $threshold;
        $this->severity = $severity;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Broadcasting ItemCountExeed event', [
                'current_count' => $this->currentCount,
                'threshold' => $this->threshold,
                'severity' => $this->severity
            ]);

            // Broadcast the event
            event(new ItemCountExeed(
                currentCount: $this->currentCount,
                threshold: $this->threshold,
                severity: $this->severity
            ));

            Log::info('ItemCountExeed event broadcasted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to broadcast ItemCountExeed event', [
                'error' => $e->getMessage(),
                'current_count' => $this->currentCount,
                'threshold' => $this->threshold,
                'severity' => $this->severity
            ]);

            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BroadcastItemCountExceeded job failed permanently', [
            'error' => $exception->getMessage(),
            'current_count' => $this->currentCount,
            'threshold' => $this->threshold,
            'severity' => $this->severity
        ]);
    }
}
