<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // When order status changes to "completed" (Paid/Completed)
        if ($order->isDirty('status') && $order->status === 'completed') {
            try {
                foreach ($order->items as $item) {
                    // Subtract stock: negative quantity for export
                    $this->inventoryService->handleStockChange(
                        $item->product_id,
                        -$item->quantity,
                        'export',
                        "Order #{$order->id} payment completed",
                        $order->user_id
                    );
                }
            } catch (\Exception $e) {
                Log::error("Failed to update inventory for order #{$order->id}: " . $e->getMessage());
                // In a real scenario, you might want to prevent the status change if stock fails,
                // but since it's an observer, the update already happened.
                // Ideally this check should be in a Service before updating the order.
            }
        }
    }
}
