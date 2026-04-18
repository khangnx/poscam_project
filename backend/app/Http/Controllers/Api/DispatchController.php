<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Events\OrderDispatchUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispatchController extends Controller
{
    /**
     * Get active orders for the dispatch display.
     */
    public function index()
    {
        $orders = Order::with('items.product', 'preparer')
            ->whereIn('status', ['paid', 'preparing'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Update the status of an order (e.g. 'paid' -> 'preparing' -> 'completed').
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:preparing,completed,ready',
        ]);

        $order = Order::findOrFail($id);
        $oldStatus = $order->status;
        $newStatus = $request->status;

        if ($oldStatus === $newStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Order is already in this status'
            ], 409);
        }

        try {
            DB::beginTransaction();

            // Status transition log
            OrderStatusLog::create([
                'order_id' => $order->id,
                'user_id' => $request->user()?->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
            ]);

            // If starting preparation, assign preparer
            if ($newStatus === 'preparing') {
                $order->preparer_id = $request->user()?->id;
            }

            // If completing, record time
            if ($newStatus === 'completed') {
                $order->completed_at = now();
            }

            $order->status = $newStatus;
            $order->save();

            DB::commit();

            // Broadcast to dispatch screens
            try {
                broadcast(new OrderDispatchUpdated($order));
            } catch (\Throwable $be) {
                \Illuminate\Support\Facades\Log::warning("[DispatchController] Broadcast failed: " . $be->getMessage());
                // Don't crash the whole request if only broadcasting fails
            }

            return response()->json([
                'success' => true,
                'message' => 'Order status updated',
                'data' => $order->load('preparer', 'items.product')
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("[DispatchController] Status update failed: " . $e->getMessage(), [
                'order_id' => $id,
                'new_status' => $newStatus,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status: ' . $e->getMessage()
            ], 500);
        }
    }
}
