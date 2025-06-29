<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Channel for general inventory updates (item changes, additions, etc.)
Broadcast::channel('inventory', function () {
    return true; // Allow everyone to listen to inventory updates
});

// Dedicated channel for alerts and notifications
Broadcast::channel('inventory-alerts', function () {
    return true; // Consider adding user authentication here
});

// Optional: Private channel for admin-only alerts
Broadcast::channel('inventory-admin-alerts', function ($user) {
    return $user && $user->isAdmin(); // Only admins can listen
});

// Optional: Channel for specific item updates
Broadcast::channel('inventory.item.{itemId}', function ($user, $itemId) {
    return true; // Allow listening to specific item updates
});
