<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Shift;
use App\Models\Order;
use Carbon\Carbon;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $shifts = Shift::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shifts
        ]);
    }

    public function current(Request $request)
    {
        $shift = Shift::where('user_id', $request->user()->id)
            ->whereNull('end_time')
            ->first();

        if ($shift) {
            $endTime = Carbon::now();
            $salesSummary = Order::where('shift_id', $shift->id)
                ->where('status', 'completed')
                ->selectRaw('payment_method, SUM(total_amount) as total')
                ->groupBy('payment_method')
                ->get()
                ->pluck('total', 'payment_method');

            $shift->sales_summary = [
                'cash' => $salesSummary['cash'] ?? 0,
                'transfer' => $salesSummary['transfer'] ?? 0,
                'card' => $salesSummary['card'] ?? 0,
                'momo' => $salesSummary['momo'] ?? 0,
                'apple_pay' => $salesSummary['apple_pay'] ?? 0,
                'total_revenue' => $salesSummary->sum()
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $shift
        ]);
    }

    public function start(Request $request)
    {
        $request->validate([
            'start_cash' => 'required|numeric|min:0'
        ]);

        $activeShift = Shift::where('user_id', $request->user()->id)
            ->whereNull('end_time')
            ->first();

        if ($activeShift) {
            return response()->json(['success' => false, 'message' => 'Bạn đã có một ca làm việc đang mở.'], 400);
        }

        $shift = Shift::create([
            'user_id' => $request->user()->id,
            'start_time' => Carbon::now(),
            'start_cash' => $request->start_cash,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bắt đầu ca thành công.',
            'data' => $shift
        ]);
    }

    public function end(Request $request)
    {
        $request->validate([
            'end_cash' => 'required|numeric|min:0',
            'reason' => 'nullable|string'
        ]);

        $shift = Shift::where('user_id', $request->user()->id)
            ->whereNull('end_time')
            ->first();

        if (!$shift) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy ca làm việc đang mở.'], 400);
        }

        $endTime = Carbon::now();

        // Calculate sales breakdown
        $sales = Order::where('shift_id', $shift->id)
            ->where('status', 'completed')
            ->selectRaw('payment_method, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->pluck('total', 'payment_method');

        $totalCashSales = $sales['cash'] ?? 0;
        $totalRevenue = $sales->sum();
        $totalNonCashSales = $totalRevenue - $totalCashSales;

        // Formula: Gap = Actual Cash - (Opening Balance + Cash Sales)
        $expectedCash = $shift->start_cash + $totalCashSales;
        $balanceGap = $request->end_cash - $expectedCash;

        if ($balanceGap != 0 && empty($request->reason)) {
            return response()->json([
                'success' => false, 
                'message' => 'Số tiền thực tế chênh lệch so with hệ thống. Vui lòng nhập lý do.',
                'require_reason' => true,
                'system_expected' => $expectedCash,
                'gap' => $balanceGap
            ], 400);
        }

        $shift->update([
            'end_time' => $endTime,
            'end_cash' => $request->end_cash,
            'total_cash_sales' => $totalCashSales,
            'total_non_cash_sales' => $totalNonCashSales,
            'total_revenue' => $totalRevenue,
            'balance_gap' => $balanceGap,
            'reason' => $request->reason
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kết ca thành công.',
            'data' => $shift
        ]);
    }
}
