<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('customer.{customerId}', function ($user, $customerId) {
    // Allow the customer themselves or staff/admin of the same tenant
    $customer = \App\Models\Customer::find($customerId);
    if (!$customer) return false;
    
    return (int) $user->tenant_id === (int) $customer->tenant_id;
});

Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    // Allow any staff/admin of the same tenant
    return (int) $user->tenant_id === (int) $tenantId;
});
