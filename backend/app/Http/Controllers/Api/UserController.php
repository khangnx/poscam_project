<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Shift;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Users belonging to current tenant
        $users = User::with('roles')
            ->where('tenant_id', $request->user()->tenant_id)
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('User creation request:', $request->all());
        \Illuminate\Support\Facades\Log::info('Avatar file:', [$request->file('avatar')]);

        $tenantId = $request->user()->tenant_id;
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                })
            ],
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string|exists:roles,name',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048' // max 2MB
        ]);

        $data = $request->only(['name', 'email', 'phone']);
        $data['tenant_id'] = $tenantId;
        $data['password'] = Hash::make('123456');
        $data['is_active'] = $request->input('is_active', true);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        $user = User::create($data);
        $user->assignRole($request->role);

        return response()->json([
            'success' => true,
            'message' => 'Tạo nhân viên thành công',
            'data' => $user->load('roles')
        ]);
    }

    public function update(Request $request, User $user)
    {
        if ($user->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        \Illuminate\Support\Facades\Log::info('User update request ID: ' . $user->id, $request->all());
        \Illuminate\Support\Facades\Log::info('Avatar file present:', [$request->hasFile('avatar')]);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('users')->where(function ($query) use ($user) {
                    return $query->where('tenant_id', $user->tenant_id)->whereNull('deleted_at');
                })->ignore($user->id)
            ],
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|required|string|exists:roles,name',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'sometimes|boolean',
            'password' => 'nullable|string|min:6'
        ]);

        $data = $request->only(['name', 'email', 'phone', 'is_active']);
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && str_starts_with($user->avatar, '/storage/avatars/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        $user->update($data);

        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật nhân viên thành công',
            'data' => $user->load('roles')
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Kiểm tra xem nhân viên có đang trong ca làm việc không
        $activeShift = Shift::where('user_id', $user->id)
            ->whereNull('end_time')
            ->exists();

        if ($activeShift) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể kết liễu: Nhân viên này đang trong một ca làm việc mở. Vui lòng kết ca trước.'
            ], 400);
        }

        $user->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa nhân viên thành công'
        ]);
    }

    public function assignRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name'
        ]);

        if ($user->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // We replace existing roles with the new single role. 
        // Sync Roles will override the existing role array in Spatie.
        $user->syncRoles([$request->role]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật vai trò nhân viên thành công!',
            'data' => $user->load('roles')
        ]);
    }
}
