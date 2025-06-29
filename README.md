# Laravel Inventory Broadcasting System

A real-time inventory management system with WebSocket broadcasting using Laravel, Redis, and PostgreSQL notifications.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Endpoints](#api-endpoints)
- [WebSocket Events](#websocket-events)
- [Testing](#testing)
- [Deployment](#deployment)
- [Troubleshooting](#troubleshooting)

## 🎯 Overview

This Laravel application provides real-time inventory monitoring with automatic notifications when item counts exceed predefined thresholds. The system uses:

- **PostgreSQL** for database with triggers for real-time monitoring
- **Redis** for broadcasting and caching
- **WebSocket** for real-time frontend updates
- **Queue Jobs** for reliable message delivery

### Architecture Flow

```
PostgreSQL Trigger → Laravel Command → Service Layer → Queue Job → Event Broadcasting → WebSocket → Frontend
```

## ✨ Features

- ✅ Real-time inventory monitoring
- ✅ Automatic threshold alerts (warning, high, critical)
- ✅ WebSocket broadcasting for instant UI updates
- ✅ PostgreSQL NOTIFY/LISTEN for database-level monitoring
- ✅ Queue-based reliable message delivery
- ✅ CORS-enabled API for frontend integration
- ✅ Configurable severity levels
- ✅ Comprehensive logging and error handling

## 📋 System Requirements

- **PHP** 8.2+
- **Laravel** 11+
- **PostgreSQL** 12+
- **Redis** 6.0+
- **Node.js** 16+ (for frontend WebSocket client)
- **Composer** 2.0+

## 🚀 Installation

### 1. Clone and Setup Laravel

```bash
# Clone the repository
git clone <repository-url>
cd inventory-be-websocket

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 2. Database Setup

#### PostgreSQL Installation
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install postgresql postgresql-contrib

# macOS
brew install postgresql
brew services start postgresql

# Create database and user
sudo -u postgres psql
CREATE DATABASE inventory_db;
CREATE USER inventory_user WITH PASSWORD 'your_password';
GRANT ALL PRIVILEGES ON DATABASE inventory_db TO inventory_user;
\q
```

#### Database Configuration
Update your `.env` file:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=inventory_db
DB_USERNAME=inventory_user
DB_PASSWORD=your_password
```

### 3. Redis Installation

```bash
# Ubuntu/Debian
sudo apt install redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server

# macOS
brew install redis
brew services start redis

# Test Redis
redis-cli ping
# Should return: PONG
```

### 4. Run Migrations

```bash
# Run database migrations
php artisan migrate

# Seed sample data (optional)
php artisan db:seed
```

## ⚙️ Configuration

### 1. Environment Configuration

Update your `.env` file with the following configurations:

```env
# Application
APP_NAME="Inventory Broadcasting System"
APP_ENV=local
APP_KEY=base64:your-generated-key
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=inventory_db
DB_USERNAME=inventory_user
DB_PASSWORD=your_password

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

# Broadcasting Configuration
BROADCAST_DRIVER=redis
QUEUE_CONNECTION=redis

# Inventory Settings
INVENTORY_NOTIFICATION_THRESHOLD=20

# CORS Settings (for frontend integration)
FRONTEND_URL=http://localhost:3000
```

### 2. Broadcasting Configuration

The system supports multiple broadcasting drivers. Configure in `config/broadcasting.php`:

#### Option A: Redis Broadcasting (Recommended for development)
```env
BROADCAST_DRIVER=redis
```

#### Option B: Pusher (for production)
```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=your-cluster
```

#### Option C: Log Driver (for testing)
```env
BROADCAST_DRIVER=log
```

### 3. Inventory Configuration

Customize inventory settings in `config/inventory.php`:

```php
return [
    'notification_threshold' => env('INVENTORY_NOTIFICATION_THRESHOLD', 20),
    'severity_levels' => [
        'info' => ['threshold_multiplier' => 0.8, 'color' => '#17a2b8'],
        'warning' => ['threshold_multiplier' => 1.0, 'color' => '#ffc107'],
        'high' => ['threshold_multiplier' => 1.2, 'color' => '#fd7e14'],
        'critical' => ['threshold_multiplier' => 1.5, 'color' => '#dc3545'],
    ],
    'database' => [
        'listen_channel' => 'items_count_reached',
        'check_interval_ms' => 1000,
    ],
];
```

### 4. CORS Configuration

For frontend integration, CORS is configured in `config/cors.php`:

```php
return [
    'paths' => ['api/*', 'broadcasting/*', 'sanctum/csrf-cookie'],
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:8080',
        'http://192.168.8.111:3000', // Your local network IP
    ],
    'allowed_origins_patterns' => [
        '/^http:\/\/localhost:\d+$/',
        '/^http:\/\/127\.0\.0\.1:\d+$/',
        '/^http:\/\/192\.168\.\d{1,3}\.\d{1,3}:\d+$/',
    ],
    'supports_credentials' => true,
];
```

## 🏃‍♂️ Usage

### 1. Start Required Services

```bash
# Terminal 1: Start Laravel development server
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2: Start queue worker for broadcasting
php artisan queue:work redis --daemon

# Terminal 3: Start PostgreSQL notification listener
php artisan items:listen

# Terminal 4: Start Redis server (if not running as service)
redis-server
```

### 2. Verify Services

Check that all services are running:

```bash
# Check Laravel server
curl http://localhost:8000

# Check Redis
redis-cli ping

# Check PostgreSQL
psql -d inventory_db -c "SELECT version();"

# Check queue jobs
php artisan queue:monitor
```

### 3. Frontend Integration

#### Install Frontend Dependencies
```bash
npm install laravel-echo pusher-js socket.io-client
```

#### Basic WebSocket Client
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: false,
    wsHost: window.location.hostname,
    wsPort: 6001,
});

// Listen for inventory alerts
window.Echo.channel('inventory-alerts')
    .listen('.item.count.exceeded', (e) => {
        console.log('Alert received:', e);
        // Handle the notification in your UI
        displayAlert(e.message, e.severity);
    });
```

## 📡 API Endpoints

### Items API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/items` | Get all items |
| POST | `/api/items` | Create new item |
| GET | `/api/items/{id}` | Get specific item |
| PUT | `/api/items/{id}` | Update item |
| DELETE | `/api/items/{id}` | Delete item |

### Test Endpoints (Development only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/test-broadcast` | Manually trigger broadcast |
| GET | `/test-service-broadcast` | Test via service layer |
| GET | `/test-item-count` | Get current item count |

### Example API Usage

```bash
# Get all items
curl -X GET http://localhost:8000/api/items \
  -H "Content-Type: application/json"

# Create new item
curl -X POST http://localhost:8000/api/items \
  -H "Content-Type: application/json" \
  -d '{"name": "Sample Item", "quantity": 10, "price": 29.99}'

# Test broadcast
curl -X GET http://localhost:8000/test-broadcast
```

## 📢 WebSocket Events

### Event Types

| Event | Channel | Description |
|-------|---------|-------------|
| `item.count.exceeded` | `inventory-alerts` | Fired when item count exceeds threshold |
| `item.updated` | `inventory` | Fired when an item is updated |

### Event Payload Structure

#### Item Count Exceeded Event
```json
{
  "message": "Item count exceeded the limit.",
  "current_count": 25,
  "threshold": 20,
  "severity": "warning",
  "timestamp": "2025-06-29T12:00:00.000000Z",
  "type": "count_exceeded_alert"
}
```

#### Item Updated Event
```json
{
  "id": 1,
  "name": "Updated Item",
  "quantity": 15,
  "updated_at": "2025-06-29T12:00:00.000000Z"
}
```

### Severity Levels

| Level | Threshold Multiplier | Color | Description |
|-------|---------------------|--------|-------------|
| `info` | 0.8x | Blue | Below threshold |
| `warning` | 1.0x | Yellow | At threshold |
| `high` | 1.2x | Orange | 20% above threshold |
| `critical` | 1.5x | Red | 50% above threshold |

## 🧪 Testing

### Manual Testing

```bash
# Test database trigger by adding items
php artisan tinker
>>> App\Models\Item::factory(21)->create()

# Test broadcasting directly
>>> event(new App\Events\ItemCountExeed(25, 20, 'critical'))

# Test service layer
>>> $service = new App\Services\InventoryNotificationService()
>>> $service->handleCountThresholdReached(20)
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=InventoryNotificationTest

# Test with coverage
php artisan test --coverage
```

### Monitor Real-time Activity

```bash
# Monitor Redis activity
redis-cli monitor

# Monitor Laravel logs
tail -f storage/logs/laravel.log

# Monitor queue jobs
php artisan queue:monitor
```

## 🚀 Deployment

### Production Environment Setup

#### 1. Environment Configuration
```env
APP_ENV=production
APP_DEBUG=false
BROADCAST_DRIVER=pusher
QUEUE_CONNECTION=redis
LOG_CHANNEL=stack
```

#### 2. Process Management

Using **Supervisor** for queue workers:

```ini
# /etc/supervisor/conf.d/inventory-queue.conf
[program:inventory-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/app/artisan queue:work redis --sleep=3 --tries=3
directory=/path/to/app
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/inventory-queue.log
```

Using **Supervisor** for PostgreSQL listener:

```ini
# /etc/supervisor/conf.d/inventory-listener.conf
[program:inventory-listener]
command=php /path/to/app/artisan items:listen
directory=/path/to/app
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/inventory-listener.log
```

#### 3. Web Server Configuration

**Nginx Configuration:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/app/public;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Performance Optimization

```bash
# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize Composer
composer install --optimize-autoloader --no-dev
```

## 🔍 Troubleshooting

### Common Issues

#### 1. Redis Connection Failed
```bash
# Check Redis status
sudo systemctl status redis
redis-cli ping

# Check Redis configuration
redis-cli config get "*"

# Check Laravel Redis connection
php artisan tinker
>>> Redis::ping()
```

#### 2. PostgreSQL Notifications Not Working
```bash
# Check PostgreSQL connection
php artisan tinker
>>> DB::connection('pgsql')->select('SELECT version()')

# Test NOTIFY manually
psql -d inventory_db -c "NOTIFY items_count_reached, 'test message';"

# Check function exists
psql -d inventory_db -c "\df check_items_count"
```

#### 3. Queue Jobs Not Processing
```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Monitor queue in real-time
php artisan queue:monitor
```

#### 4. Broadcasting Not Working
```bash
# Check broadcasting configuration
php artisan config:show broadcasting

# Test event firing
php artisan tinker
>>> broadcast(new App\Events\ItemCountExeed(25, 20, 'warning'))

# Check logs
tail -f storage/logs/laravel.log
```

#### 5. CORS Issues
```bash
# Check CORS configuration
php artisan config:show cors

# Test CORS manually
curl -H "Origin: http://localhost:3000" \
     -H "Access-Control-Request-Method: GET" \
     -H "Access-Control-Request-Headers: X-Requested-With" \
     -X OPTIONS \
     http://localhost:8000/api/items
```

### Debug Commands

```bash
# Check system status
php artisan about

# Check configuration
php artisan config:show

# Check routes
php artisan route:list

# Check events
php artisan event:list

# Check jobs
php artisan queue:monitor

# Clear all caches
php artisan optimize:clear
```

### Logging

Monitor logs for debugging:

```bash
# Laravel application logs
tail -f storage/logs/laravel.log

# Redis logs
tail -f /var/log/redis/redis-server.log

# PostgreSQL logs
tail -f /var/log/postgresql/postgresql-*.log

# Nginx/Apache logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log
```

## 📞 Support

For issues and questions:

1. Check the [Troubleshooting](#troubleshooting) section
2. Review Laravel documentation: https://laravel.com/docs
3. Check Redis documentation: https://redis.io/documentation
4. Review PostgreSQL documentation: https://www.postgresql.org/docs/

## 📝 License

This project is licensed under the MIT License.

---

**Happy Coding! 🚀**
