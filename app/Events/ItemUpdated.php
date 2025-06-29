<?php

namespace App\Events;

use App\Models\Item;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $item;

    public function __construct(Item $item)
    {
        $this->item = $item;
    }

    public function broadcastOn()
    {
        return new Channel('inventory');
    }

    public function broadcastWith()
    {
        return $this->item->toArray();
    }
    
}
