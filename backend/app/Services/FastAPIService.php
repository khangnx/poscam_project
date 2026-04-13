<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FastAPIService
{
    protected string $baseUrl;

    public function __construct()
    {
        // Internal docker network name or config variable
        $this->baseUrl = config('services.fastapi.url', 'http://worker:8000');
    }

    /**
     * Send a print job to the Fastapi worker.
     * 
     * @param array $jobData Data to be sent to FastAPI worker.
     * @return array Response from the Fastapi service.
     */
    public function sendPrintJob(array $jobData): array
    {
        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/api/print", $jobData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Job sent successfully',
                    'data' => $response->json()
                ];
            }

            Log::error('FastAPI Print Job Failed - Status: ' . $response->status(), [
                'response' => $response->body(),
                'payload' => $jobData
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process print job on FastAPI service'
            ];

        } catch (\Exception $e) {
            Log::error('FastAPI Connection Error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Could not connect to FastAPI print service'
            ];
        }
    }
}
