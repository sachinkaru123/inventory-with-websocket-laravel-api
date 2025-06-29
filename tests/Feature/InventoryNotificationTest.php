<?php

namespace Tests\Feature;

use App\Jobs\BroadcastItemCountExceeded;
use App\Services\InventoryNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InventoryNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_notification_service_dispatches_job()
    {
        Queue::fake();

        $service = new InventoryNotificationService();
        $service->handleCountThresholdReached(20);

        Queue::assertPushed(BroadcastItemCountExceeded::class);
    }

    public function test_broadcast_job_executes_successfully()
    {
        $job = new BroadcastItemCountExceeded(
            currentCount: 25,
            threshold: 20,
            severity: 'warning'
        );

        // This should not throw any exceptions
        $job->handle();

        $this->assertTrue(true); // If we get here, the job executed successfully
    }

    public function test_severity_determination()
    {
        $service = new InventoryNotificationService();
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('determineSeverity');
        $method->setAccessible(true);

        $this->assertEquals('warning', $method->invoke($service, 20, 20));
        $this->assertEquals('high', $method->invoke($service, 24, 20));
        $this->assertEquals('critical', $method->invoke($service, 30, 20));
        $this->assertEquals('info', $method->invoke($service, 15, 20));
    }
}
