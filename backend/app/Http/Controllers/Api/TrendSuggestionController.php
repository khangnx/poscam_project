<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrendSuggestion;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TrendSuggestionController extends Controller
{
    /**
     * Get list of active trend suggestions.
     */
    public function index()
    {
        $trends = TrendSuggestion::latest()->take(10)->get();

        return response()->json([
            'success' => true,
            'data' => $trends
        ]);
    }

    /**
     * Sync trends from Python worker.
     * Secured by X-Internal-Secret.
     */
    public function sync(Request $request)
    {
        $secret = $request->header('X-Internal-Secret');
        if ($secret !== 'worker-secret-token') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'trends' => 'required|array',
            'trends.*.item_name' => 'required|string',
            'trends.*.trend_score' => 'required|integer',
            'trends.*.source_url' => 'nullable|string',
            'trends.*.recommendation_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        foreach ($request->trends as $trendData) {
            TrendSuggestion::updateOrCreate(
                ['item_name' => $trendData['item_name']],
                [
                    'trend_score' => $trendData['trend_score'],
                    'source_url' => $trendData['source_url'] ?? null,
                    'recommendation_reason' => $trendData['recommendation_reason'] ?? null,
                    // keep existing status and product_id if item exists
                ]
            );
        }

        return response()->json(['success' => true, 'message' => 'Trends synced successfully']);
    }

    /**
     * Convert a trend suggestion into a draft product.
     */
    public function addToMenu(Request $request, $id)
    {
        $trend = TrendSuggestion::findOrFail($id);

        if ($trend->status === 'added' && $trend->product_id) {
            return response()->json(['success' => false, 'message' => 'Món này đã được thêm vào menu rồi.'], 400);
        }

        $tenantId = auth()->user()->tenant_id;

        DB::beginTransaction();
        try {
            // Create a draft product
            $sku = 'TREND-' . Str::upper(Str::random(6));
            
            $product = Product::create([
                'tenant_id' => $tenantId,
                'name' => $trend->item_name,
                'sku' => $sku,
                'selling_price' => 0, // Placeholder
                'cost_price' => 0,
                'status' => 'inactive', // Draft
                'description' => 'Gợi ý từ AI Trend Discovery: ' . $trend->recommendation_reason
            ]);

            $trend->update([
                'status' => 'added',
                'product_id' => $product->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đã thêm bản nháp sản phẩm vào Menu!',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi khi tạo sản phẩm: ' . $e->getMessage()], 500);
        }
    }
}
