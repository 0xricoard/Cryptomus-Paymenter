<?php

namespace App\Extensions\Gateways\Cryptomus;

use App\Classes\Extensions\Gateway;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class Cryptomus extends Gateway
{
    public function getMetadata()
    {
        return [
            'display_name' => 'Cryptomus',
            'version'      => '1.0.2',
            'author'       => '0xricoard',
            'website'      => 'https://servermikro.com',
        ];
    }

    public function getConfig()
    {
        return [
            [
                'name'         => 'api_key',
                'friendlyName' => 'API Key',
                'type'        => 'text',
                'required'    => true,
            ],
            [
                'name'         => 'merchant_id',
                'friendlyName' => 'Merchant ID',
                'type'        => 'text',
                'required'    => true,
            ],
            [
                'name'         => 'currency',
                'friendlyName' => 'Currency',
                'type'        => 'text',
                'required'    => true,
                'default'     => 'USD',
            ],
        ];
    }

    public function pay($total, $products, $invoiceId)
    {
        $cacheKey = "cryptomus_payment_url_$invoiceId";
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $url = 'https://api.cryptomus.com/v1/payment';
        $apiKey = trim(ExtensionHelper::getConfig('Cryptomus', 'api_key'));
        $merchantId = trim(ExtensionHelper::getConfig('Cryptomus', 'merchant_id'));
        $currency = trim(ExtensionHelper::getConfig('Cryptomus', 'currency'));

        // Prepare payment request data
        $data = [
            'amount' => number_format($total, 2, '.', ''),
            'currency' => $currency,
            'order_id' => (string) $invoiceId,
            'url_callback' => url('/extensions/cryptomus/webhook'),
            'url_return' => route('clients.invoice.show', $invoiceId),
            'url_success' => route('clients.invoice.show', $invoiceId),
        ];

        // Generate signature
        $sign = md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)) . $apiKey);

        // Send request to Cryptomus
        $response = Http::withHeaders([
            'merchant' => $merchantId,
            'sign' => $sign,
        ])->post($url, $data);

        // Handle response
        if ($response->successful()) {
            $paymentUrl = $response->json()['result']['url'] ?? null;
            if ($paymentUrl) {
                Cache::put($cacheKey, $paymentUrl, 3600);
                return $paymentUrl;
            }
        }

        Log::error('Cryptomus Payment Error', ['response' => $response->body()]);
        return false;
    }

    public function webhook(Request $request)
    {
        $apiKey = trim(ExtensionHelper::getConfig('Cryptomus', 'api_key'));

        // Get raw request body
        $rawContent = file_get_contents('php://input');
        $data = json_decode($rawContent, true);

        // Log incoming webhook for debugging
        Log::debug('Cryptomus Webhook Data', ['raw' => $rawContent, 'decoded' => $data]);

        // Ensure JSON is valid
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Invalid JSON format in webhook', ['error' => json_last_error_msg()]);
            return response()->json(['success' => false, 'message' => 'Invalid JSON data'], 400);
        }

        // Check if the signature exists
        if (!isset($data['sign'])) {
            Log::error('Missing sign in webhook');
            return response()->json(['success' => false, 'message' => 'Missing sign'], 400);
        }

        $receivedSign = $data['sign'];
        unset($data['sign']); // Remove sign before hashing

        // Generate signature based on Cryptomus documentation
        $generatedSign = md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)) . $apiKey);

        // Log signature verification process
        Log::debug('Cryptomus Webhook Signature Verification', [
            'received_sign' => $receivedSign,
            'generated_sign' => $generatedSign,
        ]);

        // Verify the signature
        if (!hash_equals($generatedSign, $receivedSign)) {
            Log::error('Invalid webhook signature', [
                'received' => $receivedSign,
                'expected' => $generatedSign,
            ]);
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        // Extract required fields
        $orderId = $data['order_id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$orderId || !$status) {
            Log::error('Missing required parameters in webhook', $data);
            return response()->json(['success' => false, 'message' => 'Missing parameters'], 400);
        }

        // Process payment based on status
        if ($status === 'paid') {
            ExtensionHelper::paymentDone($orderId, 'Cryptomus');
            return response()->json(['success' => true]);
        } elseif (in_array($status, ['expired', 'failed', 'cancel'])) {
            Log::warning('Cryptomus Payment Not Completed', ['order_id' => $orderId, 'status' => $status]);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Unknown status'], 400);
    }
}
