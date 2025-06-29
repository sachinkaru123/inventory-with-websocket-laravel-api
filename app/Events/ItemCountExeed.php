<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemCountExeed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $threshold;
    public $currentCount;
    public $severity;

    /**
     * Create a new event instance.
     */
    public function __construct($currentCount = null, $threshold = null, $severity = 'warning')
    {
        $this->message = 'Item count exceeded the limit.';
        $this->currentCount = $currentCount;
        $this->threshold = $threshold;
        $this->severity = $severity;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        // Use a dedicated channel for alerts/notifications
        return new Channel('inventory-alerts');
    }

    /**
     * Customize the broadcast event name
     */
    public function broadcastAs()
    {
        return 'item.count.exceeded';
    }

    /**
     * Data to broadcast with the event
     */
    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'current_count' => $this->currentCount,
            'threshold' => $this->threshold,
            'severity' => $this->severity,
            'timestamp' => now()->toISOString(),
            'type' => 'count_exceeded_alert'
        ];
    }
}
