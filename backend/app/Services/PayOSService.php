<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayOSService
{
    private string $clientId;
    private string $apiKey;
    private string $checksumKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId = env('PAYOS_CLIENT_ID', '');
        $this->apiKey = env('PAYOS_API_KEY', '');
        $this->checksumKey = env('PAYOS_CHECKSUM_KEY', '');
        $this->baseUrl = 'https://api-merchant.payos.vn';
    }

    /**
     * Create a payment link for an order.
     */
    public function createPaymentLink(Order $order): ?array
    {
        $orderCode = $order->id;
        $amount = (int) $order->total_amount;
        $description = 'SHOPPAY ' . $order->id;
        $returnUrl = env('APP_URL') . '/payment/success';
        $cancelUrl = env('APP_URL') . '/payment/cancel';

        // MOCK MODE: If keys are not configured, return a dummy QR code for local testing
        if (empty($this->clientId) || $this->clientId === 'your_client_id') {
            return [
                'code' => '00',
                'desc' => 'success',
                'data' => [
                    'amount' => $amount,
                    'description' => $description,
                    'orderCode' => $orderCode,
                    'status' => 'PENDING',
                    'qrCode' => 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . urlencode("MOCK_QR_ORDER_" . $orderCode)
                ]
            ];
        }

        $data = [
            'orderCode' => $orderCode,
            'amount' => $amount,
            'description' => $description,
            'cancelUrl' => $cancelUrl,
            'returnUrl' => $returnUrl,
        ];

        // Sort data by key and create signature
        ksort($data);
        $signatureString = "";
        foreach ($data as $key => $value) {
            $signatureString .= ($signatureString == "" ? "" : "&") . $key . "=" . $value;
        }

        $signature = hash_hmac('sha256', $signatureString, $this->checksumKey);
        $data['signature'] = $signature;

        try {
            $response = Http::withHeaders([
                'x-client-id' => $this->clientId,
                'x-api-key' => $this->apiKey,
            ])->post($this->baseUrl . '/v2/payment-requests', $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('PayOS Create Link Failed: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('PayOS Connection Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify Webhook Signature from PayOS.
     */
    public function verifyWebhookData(array $data): bool
    {
        $signature = $data['signature'] ?? '';
        unset($data['signature']);

        ksort($data);
        $signatureString = "";
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // For PayOS items or nested data, they usually sort and stringify
                // However PayOS webhook for v2 usually has flattened data for signature
                // or specific documentation on nested objects.
                // Assuming standard flattened for now based on most VietQR services.
                continue; 
            }
            $signatureString .= ($signatureString == "" ? "" : "&") . $key . "=" . $value;
        }

        $calculatedSignature = hash_hmac('sha256', $signatureString, $this->checksumKey);

        return hash_equals($calculatedSignature, $signature);
    }

    /**
     * Get Payment Status from PayOS.
     */
    public function getPaymentStatus(int $orderCode): ?array
    {
        // MOCK MODE
        if (empty($this->clientId) || $this->clientId === 'your_client_id') {
            $order = Order::find($orderCode);
            // Simulate status based on actual DB order to make "Kiểm tra lại" act smartly in tests
            return [
                'code' => '00',
                'desc' => 'success',
                'data' => [
                    'status' => ($order && $order->status === 'completed') ? 'PAID' : 'PENDING'
                ]
            ];
        }

        try {
            $response = Http::withHeaders([
                'x-client-id' => $this->clientId,
                'x-api-key' => $this->apiKey,
            ])->get($this->baseUrl . '/v2/payment-requests/' . $orderCode);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('PayOS Get Status Failed: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('PayOS Status Connection Error: ' . $e->getMessage());
            return null;
        }
    }
}
