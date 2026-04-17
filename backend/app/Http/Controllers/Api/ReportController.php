<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Exports\SalesReportExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Get summary statistics for the reporting dashboard.
     */
    public function stats(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        
        $orders = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->with(['items.product.category'])
            ->get();

        $totalRevenue = 0;
        $totalCost = 0;
        $categoryProfit = [];
        
        foreach ($orders as $order) {
            $totalRevenue += $order->total_amount;
            foreach ($order->items as $item) {
                // Calculation: (price - cost) * quantity
                $cost = ($item->cost_at_purchase ?? 0) * $item->quantity;
                $profit = (($item->price_at_purchase ?? 0) - ($item->cost_at_purchase ?? 0)) * $item->quantity;
                $totalCost += $cost;

                // Group by category
                $catName = $item->product->category->name ?? 'Không phân loại';
                if (!isset($categoryProfit[$catName])) {
                    $categoryProfit[$catName] = 0;
                }
                $categoryProfit[$catName] += $profit;
            }
        }

        $grossProfit = $totalRevenue - $totalCost;
        $roi = $totalCost > 0 ? ($grossProfit / $totalCost) * 100 : ($grossProfit > 0 ? 100 : 0);

        // Map category profit to array for frontend
        $categorySummary = [];
        foreach ($categoryProfit as $name => $profit) {
            $categorySummary[] = ['name' => $name, 'value' => round($profit, 2)];
        }

        // Daily trend for stats
        $profitTrend = [];
        // ... (rest of the trend logic)
        
        $period = Carbon::parse($startDate)->daysUntil($endDate);
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            
            $dayRevenue = 0;
            $dayProfit = 0;
            
            $dayOrders = $orders->filter(function($o) use ($dateStr) {
                return $o->created_at->format('Y-m-d') === $dateStr;
            });
            
            foreach ($dayOrders as $order) {
                $dayRevenue += $order->total_amount;
                foreach ($order->items as $item) {
                    $dayProfit += (($item->price_at_purchase ?? 0) - ($item->cost_at_purchase ?? 0)) * $item->quantity;
                }
            }
            
            $profitTrend[] = [
                'date' => $date->format('d/m'),
                'revenue' => $dayRevenue,
                'profit' => $dayProfit
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'gross_profit' => $grossProfit,
                    'roi' => round($roi, 2),
                    'order_count' => $orders->count()
                ],
                'trend' => $profitTrend,
                'categories' => $categorySummary
            ]
        ]);
    }

    /**
     * Get top 10 profitable products.
     */
    public function topProfitableProducts(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        $topProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.tenant_id', $tenantId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->select(
                'products.name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM((order_items.price_at_purchase - order_items.cost_at_purchase) * order_items.quantity) as total_profit'),
                DB::raw('AVG(CASE WHEN order_items.cost_at_purchase > 0 THEN (order_items.price_at_purchase - order_items.cost_at_purchase) / order_items.cost_at_purchase * 100 ELSE 100 END) as avg_roi')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_profit', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topProducts
        ]);
    }

    /**
     * Get hourly sales density.
     */
    public function hourlySalesDensity(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        $hourlyStats = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('hour')
            ->orderBy('hour', 'asc')
            ->get();

        // Fill all 24 hours
        $result = [];
        for ($i = 0; $i < 24; $i++) {
            $stat = $hourlyStats->firstWhere('hour', $i);
            $result[] = [
                'hour' => sprintf('%02d:00', $i),
                'order_count' => $stat ? $stat->order_count : 0,
                'revenue' => $stat ? (float)$stat->revenue : 0
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Export sales report to Excel.
     */
    public function exportExcel(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        $fileName = 'Sales_Report_' . Carbon::parse($startDate)->format('dmY') . '_' . Carbon::parse($endDate)->format('dmY') . '.xlsx';
        
        return Excel::download(new SalesReportExport(
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
            $tenantId
        ), $fileName);
    }

    /**
     * Reprint an order as PDF.
     */
    public function reprintOrder(Order $order)
    {
        $this->authorizeTenant($order);
        
        $order->load(['items.product', 'user']);
        
        $data = [
            'order' => $order,
            'shop_name' => 'POSCam Advanced Store',
            'address' => 'Sample Address, District 1, HCM City'
        ];

        $pdf = Pdf::loadView('reports.order_reprint', $data);
        
        return $pdf->download('order_reprint_' . $order->id . '.pdf');
    }

    protected function authorizeTenant($model)
    {
        if ($model->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized access to this resource.');
        }
    }
}
