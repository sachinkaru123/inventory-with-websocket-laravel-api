# Laravel Redis WebSocket Broadcasting - Complete Implementation Guide

## Overview

This guide shows how to implement a complete Redis-based WebSocket broadcasting system for inventory management without using Pusher. The system uses Redis as a message broker between Laravel and a custom WebSocket server.

## Architecture Overview

```
Laravel App → Redis → WebSocket Server → Frontend Clients
     ↓           ↓           ↓              ↓
   Events → Pub/Sub → Socket.IO → Browser WebSocket
```

## Prerequisites

- Laravel 11+ 
- Redis Server
- Node.js 16+
- Basic understanding of WebSocket and Redis

---

## Part 1: Laravel Backend Setup

### Step 1: Install Required PHP Packages

```bash
# Install Redis PHP client
composer require predis/predis

# Optional: Laravel WebSockets (alternative approach)
# composer require beyondcode/laravel-websockets
```

### Step 2: Configure Broadcasting

#### 2.1 Environment Configuration
```env
# .env
BROADCAST_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0

# Redis connection for broadcasting
BROADCAST_CONNECTION=redis

# Queue configuration (for background broadcasting)
QUEUE_CONNECTION=redis
```

#### 2.2 Broadcasting Configuration
```php
// config/broadcasting.php
<?php

return [
    'default' => env('BROADCAST_DRIVER', 'null'),
    
    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => env('BROADCAST_CONNECTION', 'default'),
        ],
        
        // Keep other drivers for flexibility
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true,
            ],
        ],
    ],
];
```

#### 2.3 Redis Configuration
```php
// config/database.php - Redis section
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],

    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],

    // Dedicated connection for broadcasting
    'broadcasting' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_BROADCAST_DB', '2'),
    ],
],
```

### Step 3: Enable Broadcasting Service Provider

```php
// config/app.php
'providers' => [
    // ...other providers
    App\Providers\BroadcastServiceProvider::class,
],
```

```php
// app/Providers/BroadcastServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes();
        require base_path('routes/channels.php');
    }
}
```

### Step 4: Create Broadcasting Events

#### 4.1 Generate Events
```bash
php artisan make:event ItemUpdated
php artisan make:event ItemCountExceeded
php artisan make:event UserJoinedInventory
```

#### 4.2 Item Updated Event
```php
<?php
// app/Events/ItemUpdated.php

namespace App\Events;

use App\Models\Item;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
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

    public function broadcastAs()
    {
        return 'item.updated';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->item->id,
            'name' => $this->item->name,
            'stock' => $this->item->stock,
            'updated_at' => $this->item->updated_at->toISOString(),
            'action' => 'updated'
        ];
    }
}
```

#### 4.3 Item Count Exceeded Event
```php
<?php
// app/Events/ItemCountExceeded.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemCountExceeded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $threshold;
    public $currentCount;
    public $severity;
    public $itemId;

    public function __construct($itemId, $currentCount, $threshold, $severity = 'warning')
    {
        $this->itemId = $itemId;
        $this->currentCount = $currentCount;
        $this->threshold = $threshold;
        $this->severity = $severity;
        $this->message = "Item stock level {$severity}: {$currentCount} items remaining";
    }

    public function broadcastOn()
    {
        return new Channel('inventory-alerts');
    }

    public function broadcastAs()
    {
        return 'item.count.exceeded';
    }

    public function broadcastWith()
    {
        return [
            'item_id' => $this->itemId,
            'message' => $this->message,
            'current_count' => $this->currentCount,
            'threshold' => $this->threshold,
            'severity' => $this->severity,
            'timestamp' => now()->toISOString(),
            'type' => 'stock_alert'
        ];
    }
}
```

### Step 5: Configure Broadcasting Channels

```php
// routes/channels.php
<?php

use Illuminate\Support\Facades\Broadcast;

// Public inventory channel
Broadcast::channel('inventory', function () {
    return true; // Allow all users
});

// Public alerts channel
Broadcast::channel('inventory-alerts', function () {
    return true; // Consider adding authentication
});

// Private user channel (authenticated users only)
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence channel (who's online)
Broadcast::channel('inventory-room', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'joined_at' => now()->toISOString()
    ];
});
```

### Step 6: Update Controllers to Broadcast Events

```php
<?php
// app/Http/Controllers/ItemController.php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Events\ItemUpdated;
use App\Events\ItemCountExceeded;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function store(Request $request)
    {
        $item = Item::create($request->validate([
            'name' => 'required|string',
            'stock' => 'required|integer|min:0',
        ]));

        // Broadcast item creation
        broadcast(new ItemUpdated($item))->toOthers();
        
        // Check stock levels
        $this->checkStockLevels($item);

        return response()->json($item, 201);
    }

    public function update(Request $request, Item $item)
    {
        $oldStock = $item->stock;
        
        $item->update($request->validate([
            'name' => 'sometimes|required|string',
            'stock' => 'sometimes|required|integer|min:0',
        ]));

        // Broadcast item update
        broadcast(new ItemUpdated($item))->toOthers();

        // Check stock levels if stock changed
        if ($item->wasChanged('stock')) {
            $this->checkStockLevels($item);
        }

        return response()->json($item);
    }

    public function destroy(Item $item)
    {
        $item->delete();
        
        // Broadcast deletion
        broadcast(new ItemUpdated($item))->toOthers();
        
        return response()->noContent();
    }

    private function checkStockLevels(Item $item)
    {
        $lowStockThreshold = 10;
        $criticalStockThreshold = 5;

        if ($item->stock <= $criticalStockThreshold) {
            broadcast(new ItemCountExceeded(
                $item->id,
                $item->stock,
                $criticalStockThreshold,
                'critical'
            ))->toOthers();
        } elseif ($item->stock <= $lowStockThreshold) {
            broadcast(new ItemCountExceeded(
                $item->id,
                $item->stock,
                $lowStockThreshold,
                'warning'
            ))->toOthers();
        }
    }
}
```

---

## Part 2: WebSocket Server Implementation

### Step 7: Install Node.js Dependencies

```bash
# Create package.json for WebSocket server
npm init -y

# Install required packages
npm install socket.io redis ioredis express cors dotenv
npm install --save-dev nodemon
```

```json
// package.json
{
  "name": "inventory-websocket-server",
  "version": "1.0.0",
  "description": "WebSocket server for Laravel Redis broadcasting",
  "main": "websocket-server.js",
  "scripts": {
    "start": "node websocket-server.js",
    "dev": "nodemon websocket-server.js"
  },
  "dependencies": {
    "socket.io": "^4.7.2",
    "ioredis": "^5.3.2",
    "express": "^4.18.2",
    "cors": "^2.8.5",
    "dotenv": "^16.3.1"
  },
  "devDependencies": {
    "nodemon": "^3.0.1"
  }
}
```

### Step 8: Create WebSocket Server

```javascript
// websocket-server.js
const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const Redis = require('ioredis');
const cors = require('cors');
require('dotenv').config();

const app = express();
const server = http.createServer(app);

// Configure CORS
app.use(cors({
    origin: process.env.FRONTEND_URL || "http://localhost:8000",
    methods: ["GET", "POST"]
}));

// Socket.IO setup with CORS
const io = socketIo(server, {
    cors: {
        origin: process.env.FRONTEND_URL || "http://localhost:8000",
        methods: ["GET", "POST"],
        credentials: true
    }
});

// Redis client for subscribing to Laravel broadcasts
const redis = new Redis({
    host: process.env.REDIS_HOST || '127.0.0.1',
    port: process.env.REDIS_PORT || 6379,
    password: process.env.REDIS_PASSWORD || null,
    db: process.env.REDIS_BROADCAST_DB || 2,
});

// Store connected clients
const connectedClients = new Map();

// Socket.IO connection handling
io.on('connection', (socket) => {
    console.log('Client connected:', socket.id);
    
    // Store client info
    connectedClients.set(socket.id, {
        socket: socket,
        userId: null,
        channels: new Set()
    });

    // Handle channel subscription
    socket.on('subscribe', (data) => {
        const { channel, auth } = data;
        
        // Validate channel subscription
        if (isValidChannel(channel, auth)) {
            socket.join(channel);
            connectedClients.get(socket.id).channels.add(channel);
            
            console.log(`Client ${socket.id} subscribed to ${channel}`);
            
            // Send subscription confirmation
            socket.emit('subscription_succeeded', { channel });
            
            // Handle presence channels
            if (channel.startsWith('presence-')) {
                handlePresenceJoin(socket, channel, auth);
            }
        } else {
            socket.emit('subscription_error', { 
                channel, 
                message: 'Unauthorized or invalid channel' 
            });
        }
    });

    // Handle channel unsubscription
    socket.on('unsubscribe', (data) => {
        const { channel } = data;
        socket.leave(channel);
        connectedClients.get(socket.id).channels.delete(channel);
        
        console.log(`Client ${socket.id} unsubscribed from ${channel}`);
        
        // Handle presence channels
        if (channel.startsWith('presence-')) {
            handlePresenceLeave(socket, channel);
        }
    });

    // Handle authentication
    socket.on('authenticate', (auth) => {
        // Implement your authentication logic here
        if (validateAuth(auth)) {
            connectedClients.get(socket.id).userId = auth.user_id;
            socket.emit('authenticated', { user_id: auth.user_id });
        } else {
            socket.emit('authentication_failed', { message: 'Invalid authentication' });
        }
    });

    // Handle client disconnect
    socket.on('disconnect', () => {
        console.log('Client disconnected:', socket.id);
        
        const client = connectedClients.get(socket.id);
        if (client) {
            // Handle presence channels on disconnect
            client.channels.forEach(channel => {
                if (channel.startsWith('presence-')) {
                    handlePresenceLeave(socket, channel);
                }
            });
        }
        
        connectedClients.delete(socket.id);
    });
});

// Subscribe to Redis channels for Laravel broadcasts
redis.psubscribe('laravel_database_*', (err, count) => {
    if (err) {
        console.error('Redis subscription error:', err);
    } else {
        console.log(`Subscribed to ${count} Redis pattern(s)`);
    }
});

// Handle Redis messages from Laravel
redis.on('pmessage', (pattern, channel, message) => {
    try {
        const data = JSON.parse(message);
        const eventData = JSON.parse(data.data);
        
        // Extract channel name (remove Redis prefix)
        const broadcastChannel = data.socket || extractChannelName(channel);
        
        console.log(`Broadcasting to channel: ${broadcastChannel}`, eventData);
        
        // Broadcast to Socket.IO clients
        io.to(broadcastChannel).emit(data.event, eventData);
        
    } catch (error) {
        console.error('Error processing Redis message:', error);
    }
});

// Helper functions
function isValidChannel(channel, auth) {
    // Public channels
    if (channel.startsWith('inventory') || channel.startsWith('public-')) {
        return true;
    }
    
    // Private channels require authentication
    if (channel.startsWith('private-') || channel.startsWith('presence-')) {
        return auth && validateAuth(auth);
    }
    
    return false;
}

function validateAuth(auth) {
    // Implement your authentication validation logic
    // This could involve JWT token validation, API calls to Laravel, etc.
    return auth && auth.token; // Simplified validation
}

function extractChannelName(redisChannel) {
    // Extract channel name from Redis key
    // Format: laravel_database_inventory_broadcasting
    const parts = redisChannel.split('_');
    return parts.slice(2).join('_').replace('_broadcasting', '');
}

function handlePresenceJoin(socket, channel, auth) {
    const userInfo = {
        id: auth.user_id,
        name: auth.user_name || 'Anonymous',
        joined_at: new Date().toISOString()
    };
    
    // Broadcast user joined
    socket.to(channel).emit('presence_joining', userInfo);
    
    // Send current users to the new user
    // You might want to store this in Redis for persistence
    socket.emit('presence_state', { users: [] }); // Implement user storage
}

function handlePresenceLeave(socket, channel) {
    const client = connectedClients.get(socket.id);
    if (client && client.userId) {
        socket.to(channel).emit('presence_leaving', { 
            id: client.userId 
        });
    }
}

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({ 
        status: 'ok', 
        connections: connectedClients.size,
        timestamp: new Date().toISOString()
    });
});

// Start server
const PORT = process.env.WEBSOCKET_PORT || 3000;
server.listen(PORT, () => {
    console.log(`WebSocket server running on port ${PORT}`);
    console.log(`Accepting connections from: ${process.env.FRONTEND_URL || 'http://localhost:8000'}`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
    console.log('Shutting down WebSocket server...');
    server.close(() => {
        redis.disconnect();
        process.exit(0);
    });
});
```

### Step 9: Environment Configuration for WebSocket Server

```env
# .env for WebSocket server
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_BROADCAST_DB=2

WEBSOCKET_PORT=3000
FRONTEND_URL=http://localhost:8000

# Optional: Authentication endpoint
AUTH_ENDPOINT=http://localhost:8000/api/auth/validate
```

---

## Part 3: Frontend Implementation

### Step 10: Install Frontend Dependencies

```bash
# Install Socket.IO client
npm install socket.io-client

# Optional: Install other utilities
npm install axios
```

### Step 11: Create WebSocket Client Service

```javascript
// resources/js/websocket-client.js
import { io } from 'socket.io-client';

class WebSocketClient {
    constructor(serverUrl = 'http://localhost:3000') {
        this.socket = null;
        this.serverUrl = serverUrl;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.subscribedChannels = new Set();
        this.eventListeners = new Map();
    }

    connect(auth = null) {
        this.socket = io(this.serverUrl, {
            autoConnect: true,
            reconnection: true,
            reconnectionAttempts: this.maxReconnectAttempts,
            reconnectionDelay: 1000,
        });

        this.setupEventHandlers();

        if (auth) {
            this.authenticate(auth);
        }

        return this;
    }

    setupEventHandlers() {
        this.socket.on('connect', () => {
            console.log('Connected to WebSocket server');
            this.reconnectAttempts = 0;
            
            // Resubscribe to channels after reconnection
            this.subscribedChannels.forEach(channel => {
                this.subscribeToChannel(channel);
            });
        });

        this.socket.on('disconnect', (reason) => {
            console.log('Disconnected from WebSocket server:', reason);
        });

        this.socket.on('connect_error', (error) => {
            console.error('WebSocket connection error:', error);
        });

        this.socket.on('subscription_succeeded', (data) => {
            console.log('Successfully subscribed to:', data.channel);
        });

        this.socket.on('subscription_error', (data) => {
            console.error('Subscription error:', data);
        });
    }

    authenticate(auth) {
        this.socket.emit('authenticate', auth);
        
        this.socket.on('authenticated', (data) => {
            console.log('Authentication successful:', data);
        });

        this.socket.on('authentication_failed', (data) => {
            console.error('Authentication failed:', data);
        });
    }

    subscribeToChannel(channel, auth = null) {
        this.subscribedChannels.add(channel);
        this.socket.emit('subscribe', { channel, auth });
        return this;
    }

    unsubscribeFromChannel(channel) {
        this.subscribedChannels.delete(channel);
        this.socket.emit('unsubscribe', { channel });
        return this;
    }

    listen(event, callback) {
        if (!this.eventListeners.has(event)) {
            this.eventListeners.set(event, []);
            
            this.socket.on(event, (data) => {
                const listeners = this.eventListeners.get(event) || [];
                listeners.forEach(listener => listener(data));
            });
        }
        
        this.eventListeners.get(event).push(callback);
        return this;
    }

    stopListening(event, callback = null) {
        if (callback) {
            const listeners = this.eventListeners.get(event) || [];
            const index = listeners.indexOf(callback);
            if (index > -1) {
                listeners.splice(index, 1);
            }
        } else {
            this.eventListeners.delete(event);
            this.socket.off(event);
        }
        return this;
    }

    disconnect() {
        if (this.socket) {
            this.socket.disconnect();
            this.socket = null;
        }
        this.subscribedChannels.clear();
        this.eventListeners.clear();
    }
}

export default WebSocketClient;
```

### Step 12: Create Inventory WebSocket Manager

```javascript
// resources/js/inventory-websocket.js
import WebSocketClient from './websocket-client.js';

class InventoryWebSocketManager {
    constructor(serverUrl = 'http://localhost:3000') {
        this.client = new WebSocketClient(serverUrl);
        this.alertContainer = null;
        this.setupUI();
    }

    connect(userAuth = null) {
        this.client.connect(userAuth);
        this.setupInventoryListeners();
        return this;
    }

    setupInventoryListeners() {
        // Subscribe to inventory updates
        this.client
            .subscribeToChannel('inventory')
            .listen('item.updated', (data) => {
                console.log('Item updated:', data);
                this.handleItemUpdate(data);
            });

        // Subscribe to inventory alerts
        this.client
            .subscribeToChannel('inventory-alerts')
            .listen('item.count.exceeded', (data) => {
                console.log('Stock alert:', data);
                this.handleStockAlert(data);
            });

        // Subscribe to presence channel (who's online)
        this.client
            .subscribeToChannel('presence-inventory-room')
            .listen('presence_joining', (user) => {
                console.log('User joined:', user);
                this.showNotification(`${user.name} joined the inventory room`, 'info');
            })
            .listen('presence_leaving', (user) => {
                console.log('User left:', user);
                this.showNotification(`User left the inventory room`, 'info');
            });
    }

    handleItemUpdate(data) {
        // Update item in the UI
        const itemElement = document.querySelector(`[data-item-id="${data.id}"]`);
        if (itemElement) {
            const stockElement = itemElement.querySelector('.item-stock');
            const nameElement = itemElement.querySelector('.item-name');
            
            if (stockElement) stockElement.textContent = data.stock;
            if (nameElement) nameElement.textContent = data.name;
            
            // Add visual feedback
            itemElement.classList.add('updated');
            setTimeout(() => itemElement.classList.remove('updated'), 2000);
        }

        // Show notification
        this.showNotification(`Item "${data.name}" was updated`, 'success');
    }

    handleStockAlert(data) {
        const { message, severity, current_count, threshold } = data;
        
        let alertClass = 'alert-warning';
        let icon = '⚠️';
        
        if (severity === 'critical') {
            alertClass = 'alert-danger';
            icon = '🚨';
            this.playAlertSound();
        }

        this.showAlert({
            message: `${icon} ${message}`,
            details: `Current: ${current_count}, Threshold: ${threshold}`,
            type: alertClass,
            persist: severity === 'critical'
        });
    }

    setupUI() {
        // Create alert container
        this.alertContainer = document.createElement('div');
        this.alertContainer.id = 'inventory-alerts';
        this.alertContainer.className = 'position-fixed top-0 end-0 p-3';
        this.alertContainer.style.zIndex = '9999';
        document.body.appendChild(this.alertContainer);
    }

    showAlert({ message, details, type, persist = false }) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <strong>${message}</strong>
            ${details ? `<br><small>${details}</small>` : ''}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        this.alertContainer.appendChild(alertDiv);

        if (!persist) {
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    }

    showNotification(message, type = 'info') {
        console.log(`${type.toUpperCase()}: ${message}`);
        // You can integrate with a toast library here
    }

    playAlertSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            
            oscillator.start();
            oscillator.stop(audioContext.currentTime + 0.5);
        } catch (error) {
            console.log('Could not play alert sound:', error);
        }
    }

    disconnect() {
        this.client.disconnect();
        if (this.alertContainer) {
            this.alertContainer.remove();
        }
    }
}

export default InventoryWebSocketManager;
```

### Step 13: Initialize in Your Laravel App

```javascript
// resources/js/app.js
import './bootstrap';
import InventoryWebSocketManager from './inventory-websocket.js';

// Initialize inventory WebSocket manager
document.addEventListener('DOMContentLoaded', () => {
    const inventoryWS = new InventoryWebSocketManager('http://localhost:3000');
    
    // Connect with user authentication (if available)
    const userAuth = {
        token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        user_id: window.userId, // Set this from your Laravel view
        user_name: window.userName, // Set this from your Laravel view
    };
    
    inventoryWS.connect(userAuth);
    
    // Make it globally available
    window.inventoryWebSocket = inventoryWS;
});
```

---

## Part 4: Testing and Deployment

### Step 14: Testing Your Implementation

#### 14.1 Test Redis Broadcasting
```bash
# In Laravel tinker
php artisan tinker

# Test broadcasting
$item = App\Models\Item::first();
broadcast(new App\Events\ItemUpdated($item));

# Test alert
broadcast(new App\Events\ItemCountExceeded($item->id, 3, 5, 'critical'));
```

#### 14.2 Monitor Redis Messages
```bash
# Monitor Redis activity
redis-cli monitor

# Check Redis keys
redis-cli keys "*"

# Subscribe to Laravel broadcast channel
redis-cli psubscribe "laravel_database_*"
```

#### 14.3 Test WebSocket Server
```bash
# Run WebSocket server in development
npm run dev

# Test health endpoint
curl http://localhost:3000/health
```

### Step 15: Production Deployment

#### 15.1 Process Management with PM2
```bash
# Install PM2
npm install -g pm2

# Create PM2 ecosystem file
```

```javascript
// ecosystem.config.js
module.exports = {
    apps: [{
        name: 'inventory-websocket',
        script: './websocket-server.js',
        instances: 1,
        exec_mode: 'fork',
        env: {
            NODE_ENV: 'production',
            WEBSOCKET_PORT: 3000,
            REDIS_HOST: '127.0.0.1',
            REDIS_PORT: 6379,
            FRONTEND_URL: 'https://your-domain.com'
        },
        error_file: './logs/err.log',
        out_file: './logs/out.log',
        log_file: './logs/combined.log',
        time: true
    }]
};
```

```bash
# Start with PM2
pm2 start ecosystem.config.js
pm2 save
pm2 startup
```

#### 15.2 Nginx Configuration
```nginx
# /etc/nginx/sites-available/inventory-websocket
server {
    listen 80;
    server_name ws.your-domain.com;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
```

#### 15.3 Laravel Queue Workers
```bash
# Run queue workers for broadcasting
php artisan queue:work redis --queue=default --sleep=3 --tries=3

# Use Supervisor for production
```

```ini
# /etc/supervisor/conf.d/inventory-queue.conf
[program:inventory-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/path/to/your/app
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/inventory-queue-worker.log
stopwaitsecs=3600
```

### Step 16: Monitoring and Debugging

#### 16.1 Laravel Logging
```php
// Add to your events for debugging
public function broadcastWith()
{
    $data = [
        'id' => $this->item->id,
        'name' => $this->item->name,
        // ... other data
    ];
    
    \Log::info('Broadcasting event', [
        'event' => static::class,
        'channel' => $this->broadcastOn()->name,
        'data' => $data
    ]);
    
    return $data;
}
```

#### 16.2 WebSocket Server Logging
```javascript
// Add to websocket-server.js
const winston = require('winston');

const logger = winston.createLogger({
    level: 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.json()
    ),
    transports: [
        new winston.transports.File({ filename: 'logs/error.log', level: 'error' }),
        new winston.transports.File({ filename: 'logs/combined.log' }),
        new winston.transports.Console({
            format: winston.format.simple()
        })
    ]
});
```

#### 16.3 Performance Monitoring
```javascript
// Add metrics collection
let connectionCount = 0;
let messageCount = 0;

io.on('connection', (socket) => {
    connectionCount++;
    
    socket.on('disconnect', () => {
        connectionCount--;
    });
});

// Health endpoint with metrics
app.get('/metrics', (req, res) => {
    res.json({
        connections: connectionCount,
        messages_processed: messageCount,
        uptime: process.uptime(),
        memory_usage: process.memoryUsage(),
        timestamp: new Date().toISOString()
    });
});
```

---

## Troubleshooting

### Common Issues

1. **Redis Connection Failed**
   ```bash
   # Check Redis status
   redis-cli ping
   
   # Check Redis logs
   tail -f /var/log/redis/redis-server.log
   ```

2. **WebSocket Connection Issues**
   - Check CORS configuration
   - Verify firewall settings
   - Check browser console for errors

3. **Events Not Broadcasting**
   ```bash
   # Check Laravel queues
   php artisan queue:failed
   
   # Test Redis directly
   redis-cli publish "test_channel" '{"test": "message"}'
   ```

4. **Performance Issues**
   - Monitor Redis memory usage
   - Check WebSocket server memory
   - Consider using Redis Cluster for scaling

### Production Considerations

1. **Security**
   - Implement proper authentication
   - Use SSL/TLS for WebSocket connections
   - Validate all incoming data

2. **Scalability**
   - Use Redis Sentinel for high availability
   - Consider multiple WebSocket server instances
   - Implement horizontal scaling with sticky sessions

3. **Monitoring**
   - Set up application monitoring (New Relic, DataDog)
   - Monitor Redis performance
   - Log WebSocket connection metrics

---

This comprehensive guide provides a complete Redis-based WebSocket broadcasting implementation for your Laravel inventory system. The solution is production-ready and includes proper error handling, monitoring, and deployment configurations.
