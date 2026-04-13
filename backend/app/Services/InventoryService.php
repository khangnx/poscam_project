<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Material;
use App\Models\InventoryLog;
use App\Models\StockImport;
use App\Models\StockImportItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class InventoryService
{
    /**
     * Handle stock change for a MATERIAL and log it.
     * 
     * @param int $materialId
     * @param int $quantity
     * @param string $type [import, export, return, sale, adjustment, wastage]
     * @param string|null $note
     * @param int|null $userId
     * @param int|null $orderId
     * @return Material
     * @throws Exception
     */
    public function handleStockChange($materialId, $quantity, $type, $note = null, $userId = null, $orderId = null)
    {
        return DB::transaction(function () use ($materialId, $quantity, $type, $note, $userId, $orderId) {
            $material = Material::lockForUpdate()->find($materialId);
            
            if (!$material) {
                throw new Exception("Material not found.");
            }

            $oldStock = $material->stock;
            $newStock = $oldStock + $quantity;

            if ($newStock < 0) {
                throw new Exception("Insufficient stock for material: {$material->name}");
            }

            $material->stock = $newStock;
            $material->save();

            InventoryLog::create([
                'tenant_id'   => $material->tenant_id,
                'material_id' => $materialId,
                'user_id'     => $userId ?? Auth::id(),
                'order_id'    => $orderId,
                'type'        => $type,
                'quantity'    => $quantity,
                'old_stock'   => $oldStock,
                'new_stock'   => $newStock,
                'note'        => $note,
            ]);

            return $material;
        });
    }

    /**
     * Handle material import from a supplier with Unit Conversion support.
     * 
     * @param int $materialId
     * @param int $supplierId
     * @param float $purchaseQuantity
     * @param float $purchasePrice
     * @param float $conversionFactor
     * @param string|null $note
     * @return Material
     */
    public function importMaterial($materialId, $supplierId, $purchaseQuantity, $purchasePrice, $conversionFactor = 1, $note = null)
    {
        return DB::transaction(function () use ($materialId, $supplierId, $purchaseQuantity, $purchasePrice, $conversionFactor, $note) {
            $material = Material::lockForUpdate()->find($materialId);
            
            if (!$material) {
                throw new Exception("Material not found.");
            }

            $actualUsageQuantity = $purchaseQuantity * $conversionFactor;
            $usageCostPrice = $purchasePrice / $conversionFactor;

            $currentStock = $material->stock;
            $currentCost = $material->cost_price;
            
            $totalCurrentValue = $currentStock * $currentCost;
            $totalNewValue = $actualUsageQuantity * $usageCostPrice;
            $totalStock = $currentStock + $actualUsageQuantity;
            
            $newAverageCost = $totalStock > 0 ? ($totalCurrentValue + $totalNewValue) / $totalStock : $usageCostPrice;

            $material->supplier_id = $supplierId;
            $material->cost_price = $newAverageCost;
            
            if ($conversionFactor != $material->conversion_factor) {
                $material->conversion_factor = $conversionFactor;
            }
            $material->save();

            // Ripple effect: Update cost of any "Product" that uses this as an ingredient
            $this->updateDependentCosts($materialId);

            return $this->handleStockChange(
                $materialId,
                $actualUsageQuantity,
                'import',
                $note ?? "Nhập hàng từ NCC (Quy đổi: {$purchaseQuantity} x {$conversionFactor})",
                Auth::id()
            );
        });
    }

    public function processImport(array $data)
    {
        return DB::transaction(function () use ($data) {
            $tenantId = Auth::user()->tenant_id;
            
            $stockImport = StockImport::create([
                'tenant_id' => $tenantId,
                'supplier_id' => $data['supplier_id'] ?? null,
                'user_id' => Auth::id(),
                'import_date' => isset($data['import_date']) ? \Illuminate\Support\Carbon::parse($data['import_date']) : now(),
                'total_amount' => $data['total_amount'] ?? 0,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $subtotal = $item['quantity'] * $item['purchase_price'];
                
                StockImportItem::create([
                    'import_id' => $stockImport->id,
                    'material_id' => $item['material_id'],
                    'quantity' => $item['quantity'],
                    'purchase_unit' => $item['purchase_unit'] ?? null,
                    'purchase_price' => $item['purchase_price'],
                    'conversion_factor' => $item['conversion_factor'] ?? 1,
                    'subtotal' => $subtotal,
                ]);

                $this->importMaterial(
                    $item['material_id'],
                    $data['supplier_id'] ?? null,
                    $item['quantity'],
                    $item['purchase_price'],
                    $item['conversion_factor'] ?? 1,
                    "Nhập hàng theo phiếu #{$stockImport->id}"
                );
            }

            return $stockImport;
        });
    }

    /**
     * Deduct ingredients for a finished product based on its recipe.
     */
    public function deductIngredients(Product $product, $orderQuantity, $orderId = null)
    {
        if (!$product->recipes()->exists()) {
            return;
        }

        foreach ($product->recipes as $recipe) {
            $requiredQty = $recipe->quantity * $orderQuantity;
            $this->handleStockChange(
                $recipe->material_id,
                -$requiredQty,
                'sale',
                "Xuất kho để chế biến {$product->name} - Đơn hàng #{$orderId}",
                Auth::id(),
                $orderId
            );
        }
    }

    /**
     * Handle stock adjustment after count.
     */
    public function adjustStock($materialId, $actualStock, $note = null)
    {
        $material = Material::findOrFail($materialId);
        $diff = $actualStock - $material->stock;
        
        if ($diff == 0) return $material;

        return $this->handleStockChange(
            $materialId,
            $diff,
            'adjustment',
            $note ?? "Điều chỉnh sau kiểm kho",
            Auth::id()
        );
    }

    /**
     * Handle stock wastage (damages, expired, etc.)
     */
    public function wasteStock($materialId, $quantity, $reason = null)
    {
        return $this->handleStockChange(
            $materialId,
            -abs($quantity),
            'wastage',
            $reason ?? "Xuất hủy nguyên liệu",
            Auth::id()
        );
    }

    /**
     * Update cost price for all products that use this product as an ingredient.
     * 
     * @param int $materialId
     * @return void
     */
    public function updateDependentCosts($materialId)
    {
        $material = Material::find($materialId);
        if (!$material) return;

        // Find all recipes using this material
        $recipes = \App\Models\ProductRecipe::where('material_id', $materialId)->get();

        foreach ($recipes as $recipe) {
            $this->calculateRecipeCost($recipe->product_id);
        }
    }

    /**
     * Calculate and update the cost_price of a finished product based on its recipe.
     * 
     * @param int $productId
     * @return float
     */
    public function calculateRecipeCost($productId)
    {
        $product = Product::with('recipes.material')->find($productId);
        if (!$product) return 0;

        $totalCost = 0;
        foreach ($product->recipes as $recipe) {
            $totalCost += ($recipe->material->cost_price * $recipe->quantity);
        }

        if ($product->recipes()->exists()) {
            $product->cost_price = $totalCost;
            $product->save();
        }

        return $totalCost;
    }
}
