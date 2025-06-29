<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inventory Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for inventory-related notifications and alerts
    |
    */

    // Default threshold for item count notifications
    'notification_threshold' => env('INVENTORY_NOTIFICATION_THRESHOLD', 20),

    // Notification channels
    'notification_channels' => [
        'websocket' => true,
        'email' => env('INVENTORY_EMAIL_NOTIFICATIONS', false),
        'slack' => env('INVENTORY_SLACK_NOTIFICATIONS', false),
    ],

    // Broadcasting settings
    'broadcasting' => [
        'channel' => 'inventory-alerts',
        'event_name' => 'item.count.exceeded',
        'retry_attempts' => 3,
        'timeout_seconds' => 30,
    ],

    // Severity levels configuration
    'severity_levels' => [
        'info' => [
            'threshold_multiplier' => 0.8,
            'color' => '#17a2b8',
        ],
        'warning' => [
            'threshold_multiplier' => 1.0,
            'color' => '#ffc107',
        ],
        'high' => [
            'threshold_multiplier' => 1.2,
            'color' => '#fd7e14',
        ],
        'critical' => [
            'threshold_multiplier' => 1.5,
            'color' => '#dc3545',
        ],
    ],

    // Database settings
    'database' => [
        'listen_channel' => 'items_count_reached',
        'check_interval_ms' => 1000,
    ],
];
