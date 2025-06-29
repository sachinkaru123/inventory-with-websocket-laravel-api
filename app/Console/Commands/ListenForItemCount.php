<?php

namespace App\Console\Commands;

use App\Services\InventoryNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as FacadesLog;
use PDO;

class ListenForItemCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'items:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for PostgreSQL notifications when items count reaches 20';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Listening for items count notifications...');

        // Get the PDO connection
        $connection = DB::connection('pgsql')->getPdo();

        // Listen for the 'items_count_reached' channel
        $channelName = config('inventory.database.listen_channel', 'items_count_reached');
        $checkInterval = config('inventory.database.check_interval_ms', 1000);
        
        $connection->exec("LISTEN {$channelName}");

        while (true) {
            // Check for notifications
            $notification = $connection->pgsqlGetNotify(PDO::FETCH_ASSOC, $checkInterval);
            // FacadesLog::debug('Checking for notifications...'.$notification );
            if ($notification) {
                $this->info('Notification received: ' . $notification['payload']);
                // Call your Laravel function here
                $this->callYourLaravelFunction();
            }
        }
    }
    private function callYourLaravelFunction()
    {
        FacadesLog::info('Items count threshold reached, processing notification');
        
        try {
            $notificationService = new InventoryNotificationService();
            $threshold = config('inventory.notification_threshold', 20);
            
            // Handle the notification professionally through the service
            $notificationService->handleCountThresholdReached($threshold);
            
            FacadesLog::info('Inventory notification processed successfully');
            
        } catch (\Exception $e) {
            FacadesLog::error('Failed to process inventory notification', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
