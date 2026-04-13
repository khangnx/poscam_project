<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics.
     */
    public function stats(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $startOfMonth = Carbon::now()->startOfMonth();

        // 1. Revenue & Profit Today vs Yesterday
        $ordersToday = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereDate('created_at', $today)
            ->with('items')
            ->get();
            
        $revenueToday = $ordersToday->sum('total_amount');
        $profitToday = $ordersToday->sum(function ($order) {
            return $order->items->sum(function ($item) {
                $price = (float)($item->price_at_purchase ?? 0);
                $cost = (float)($item->cost_at_purchase ?? 0);
                return ($price - $cost) * (int)$item->quantity;
            });
        });

        $ordersYesterday = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereDate('created_at', $yesterday)
            ->with('items')
            ->get();

        $revenueYesterday = $ordersYesterday->sum('total_amount');
        $profitYesterday = $ordersYesterday->sum(function ($order) {
            return $order->items->sum(function ($item) {
                $price = (float)($item->price_at_purchase ?? 0);
                $cost = (float)($item->cost_at_purchase ?? 0);
                return ($price - $cost) * (int)$item->quantity;
            });
        });

        $revenueGrowth = $revenueYesterday > 0 ? (($revenueToday - $revenueYesterday) / $revenueYesterday) * 100 : ($revenueToday > 0 ? 100 : 0);
        $profitGrowth = $profitYesterday > 0 ? (($profitToday - $profitYesterday) / $profitYesterday) * 100 : ($profitToday > 0 ? 100 : 0);

        // 2. Success vs Cancelled Orders (All time or this month, let's do this month)
        $completedOrdersCount = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $startOfMonth)
            ->count();
            
        $cancelledOrdersCount = Order::where('tenant_id', $tenantId)
            ->where('status', 'cancelled')
            ->where('created_at', '>=', $startOfMonth)
            ->count();
            
        $totalOrdersCount = Order::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // 3. Top 5 Best Selling Products this month
        $topProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.tenant_id', $tenantId)
            ->where('orders.status', 'completed')
            ->where('orders.created_at', '>=', $startOfMonth)
            ->select('products.name', 'products.selling_price as price', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('products.id', 'products.name', 'products.selling_price')
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get();

        // 4. Revenue Trend (last 7 days)
        $sevenDaysAgo = Carbon::today()->subDays(6);
        
        $revenueTrendData = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $sevenDaysAgo)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as daily_revenue')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Fill missing days with 0
        $revenueTrend = [];
        for ($i = 0; $i < 7; $i++) {
            $dateStr = Carbon::today()->subDays(6 - $i)->format('Y-m-d');
            $dataForDate = $revenueTrendData->firstWhere('date', $dateStr);
            $revenueTrend[] = [
                'date' => Carbon::parse($dateStr)->format('d/m'),
                'revenue' => $dataForDate ? (float)$dataForDate->daily_revenue : 0
            ];
        }

        // 5. Recent 5 Orders
        $recentOrders = Order::with(['items.product.recipes.material'])
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => [
                    'today' => $revenueToday,
                    'yesterday' => $revenueYesterday,
                    'growth' => round($revenueGrowth, 2)
                ],
                'profit' => [
                    'today' => $profitToday,
                    'yesterday' => $profitYesterday,
                    'growth' => round($profitGrowth, 2)
                ],
                'orders' => [
                    'completed' => $completedOrdersCount,
                    'cancelled' => $cancelledOrdersCount,
                    'total' => $totalOrdersCount
                ],
                'top_products' => $topProducts,
                'revenue_trend' => collect($revenueTrend)->pluck('revenue'),
                'revenue_trend_labels' => collect($revenueTrend)->pluck('date'),
                'recent_orders' => $recentOrders
            ]
        ]);
    }
}
