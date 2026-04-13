<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CameraController extends Controller
{
    /**
     * Display a listing of cameras.
     * With BelongsToTenant trait functioning, this will only return
     * cameras belonging to the active tenant.
     */
    public function index()
    {
        $cameras = Camera::all();

        return response()->json([
            'success' => true,
            'message' => 'Cameras retrieved successfully',
            'data' => $cameras
        ]);
    }

    /**
     * Store a newly created camera.
     * tenant_id is automatically assigned via BelongsToTenant Trait.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rtsp_url' => ['required', 'string', 'regex:/^rtsp:\/\//i'],
            'type' => 'required|in:ip_camera,nvr',
            'location_note' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        $camera = Camera::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Camera created successfully',
            'data' => $camera
        ], 201);
    }

    /**
     * Display the specified camera.
     */
    public function show(Camera $camera)
    {
        // Protected by Global Scope
        return response()->json([
            'success' => true,
            'message' => 'Camera retrieved successfully',
            'data' => $camera
        ]);
    }

    /**
     * Update the specified camera.
     */
    public function update(Request $request, Camera $camera)
    {
        $tenantId = auth()->user()->tenant_id;
        
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'rtsp_url' => ['nullable', 'string', 'regex:/^rtsp:\/\//i', 'max:255'],
            'type' => 'nullable|in:ip_camera,nvr',
            'status' => 'nullable|in:active,inactive,offline',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $camera->update($request->all());

        if ($request->status === 'offline' && $camera->wasChanged('status')) {
            try {
                $telegram = app(\App\Services\TelegramService::class);
                $telegram->sendMessage("⚠️ *Cảnh báo*:\nCamera `{$camera->name}` bị mất kết nối!");
            } catch (\Exception $e) {
                // Ignore
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Camera updated successfully',
            'data' => $camera
        ]);
    }

    /**
     * Remove the specified camera.
     */
    public function destroy(Camera $camera)
    {
        $camera->delete();

        return response()->json([
            'success' => true,
            'message' => 'Camera deleted successfully',
            'data' => null
        ]);
    }
}
