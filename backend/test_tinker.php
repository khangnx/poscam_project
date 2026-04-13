echo json_encode(['users' => \App\Models\User::all()->toArray(), 'tenants' => \App\Models\Tenant::all()->toArray()]);
