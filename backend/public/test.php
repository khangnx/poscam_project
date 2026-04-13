<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$users = \App\Models\User::all()->toArray();
$tenants = \App\Models\Tenant::all()->toArray();

echo json_encode(['users' => $users, 'tenants' => $tenants]);
