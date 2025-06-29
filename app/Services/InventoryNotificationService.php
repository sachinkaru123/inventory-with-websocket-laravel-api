<?php

namespace App\Services;

use App\Jobs\BroadcastItemCountExceeded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryNotificationService
{
    /**
     * Handle item count threshold notifications
     */
    public function handleCountThresholdReached(int $threshold = 20): void
    {
        try {
            $currentCount = $this->getCurrentItemCount();
            
            Log::info('Processing inventory count threshold notification', [
                'current_count' => $currentCount,
                'threshold' => $threshold
            ]);
            
            // Determine severity based on how much the count exceeds the threshold
            $severity = $this->determineSeverity($currentCount, $threshold);
            
            // Dispatch the broadcast job
            dispatch(new BroadcastItemCountExceeded(
                currentCount: $currentCount,
                threshold: $threshold,
                severity: $severity
            ));
            
            Log::info('Inventory notification dispatched successfully', [
                'current_count' => $currentCount,
                'threshold' => $threshold,
                'severity' => $severity
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to handle inventory count threshold notification', [
                'error' => $e->getMessage(),
                'threshold' => $threshold
            ]);
            throw $e;
        }
    }
    
    /**
     * Get current item count from database
     */
    private function getCurrentItemCount(): int
    {
        return DB::table('items')->count();
    }
    
    /**
     * Determine severity level based on count vs threshold
     */
    private function determineSeverity(int $currentCount, int $threshold): string
    {
        $ratio = $currentCount / $threshold;
        
        if ($ratio >= 1.5) {
            return 'critical';
        } elseif ($ratio >= 1.2) {
            return 'high';
        } elseif ($ratio >= 1.0) {
            return 'warning';
        } else {
            return 'info';
        }
    }
    
    /**
     * Check if notification should be sent based on business rules
     */
    public function shouldNotify(int $currentCount, int $threshold): bool
    {
        // Only notify if count meets or exceeds threshold
        return $currentCount >= $threshold;
    }
    
    /**
     * Get notification configuration
     */
    public function getNotificationConfig(): array
    {
        return [
            'default_threshold' => config('inventory.notification_threshold', 20),
            'severity_levels' => ['info', 'warning', 'high', 'critical'],
            'retry_attempts' => 3,
            'timeout_seconds' => 30
        ];
    }
}
