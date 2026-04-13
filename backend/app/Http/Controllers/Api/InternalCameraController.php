<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use Illuminate\Http\Request;

class InternalCameraController extends Controller
{
    /**
     * Display the specified camera's stream info (RTSP URL).
     * This endpoint is meant to be called ONLY internally (e.g. from FastAPI worker within Docker network).
     * It bypasses the tenant scope since it directly accesses a resource by ID.
     */
    public function getStreamInfo($id)
    {
        // Using withoutGlobalScopes to bypass tenant scope,
        // because the worker may not have the tenant context.
        $camera = Camera::withoutGlobalScopes()->findOrFail($id);

        if (!$camera->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Camera is not active',
            ], 403);
        }

        // We make rtsp_url visible for this internal endpoint
        $camera->makeVisible(['rtsp_url']);

        return response()->json([
            'success' => true,
            'message' => 'Camera stream info retrieved',
            'data' => [
                'id' => $camera->id,
                'name' => $camera->name,
                'rtsp_url' => $camera->rtsp_url,
            ]
        ]);
    }
}
