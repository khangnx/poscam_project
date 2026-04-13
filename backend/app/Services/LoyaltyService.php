<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\CustomerGroup;
use App\Models\LoyaltyLog;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * Apply loyalty points and update customer stats based on an order.
     * Should be called within a database transaction.
     */
    public function applyLoyalty(Order $order): void
    {
        if (!$order->customer_id) {
            return;
        }

        // Lock customer for update and eager load group
        $customer = Customer::lockForUpdate()->find($order->customer_id);
        if (!$customer) {
            return;
        }
        $customer->load('group');

        $group = $customer->group;
        $earningRate = $group ? (float)$group->earning_rate : 0.01; // Default 1%

        // Points earned = Total paid amount (Net) * earning rate
        $pointsEarned = (int) floor($order->total_amount * $earningRate);

        // Update customer cumulative data
        $customer->points += $pointsEarned;
        $customer->total_spent += $order->total_amount;
        $customer->save();

        // Update order with earned points
        $order->update([
            'points_earned' => $pointsEarned,
        ]);

        // Log loyalty fluctuation
        LoyaltyLog::create([
            'customer_id'    => $customer->id,
            'order_id'       => $order->id,
            'points_changed' => $pointsEarned,
            'type'           => 'plus',
            'balance'        => $customer->points,
            'description'    => "Tích điểm đơn hàng #{$order->id} (" . ($group ? $group->name : 'Khách lẻ') . ")",
        ]);

        // Check for promotion
        $this->checkAndUpgradeGroup($customer);
    }

    /**
     * Check if customer is eligible for a higher group and upgrade.
     * Strategy: Only auto-upgrade, never auto-downgrade.
     */
    public function checkAndUpgradeGroup(Customer $customer): void
    {
        $tenantId = $customer->tenant_id;
        
        // Find the highest group the customer is eligible for
        $bestGroup = CustomerGroup::where('tenant_id', $tenantId)
            ->where('min_points', '<=', $customer->points)
            ->orderBy('min_points', 'desc')
            ->first();

        // Only upgrade if current group's min_points is lower than bestGroup's min_points
        // Or if the customer has no group yet.
        $currentMinPoints = $customer->group->min_points ?? -1;

        if ($bestGroup && $bestGroup->min_points > $currentMinPoints) {
            $oldGroupName = $customer->group->name ?? 'Mặc định';
            $customer->group_id = $bestGroup->id;
            $customer->save();

            // Log the automatic promotion
            LoyaltyLog::create([
                'customer_id'    => $customer->id,
                'points_changed' => 0,
                'type'           => 'plus',
                'balance'        => $customer->points,
                'description'    => "Thăng hạng tự động: {$oldGroupName} -> {$bestGroup->name}",
            ]);
        }
    }

    /**
     * Get discount percentage for a customer.
     */
    public function getDiscount(Customer $customer): float
    {
        if (!$customer->group_id) {
            return 0;
        }

        $customer->loadMissing('group');
        return (float) ($customer->group->discount_percent ?? 0);
    }
}
