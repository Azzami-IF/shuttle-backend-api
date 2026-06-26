<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class DompetXService
{
    /**
     * Create checkout session with DompetX
     *
     * @param int|string $bookingId
     * @param float $amount
     * @param string|null $customerEmail
     * @return array
     */
    public static function createCheckout($bookingId, $amount, $customerEmail = null)
    {
        $apiKey = config('dompetx.api_key');
        $apiSecret = config('dompetx.api_secret');
        $baseUrl = rtrim(config('dompetx.base_url'), '/');
        
        $reference = 'BOOK-' . $bookingId . '-' . time();
        
        $bodyData = [
            'amount' => (int) $amount,
            'currency' => 'IDR',
            'reference' => $reference,
            'metadata' => [
                'booking_id' => $bookingId,
            ]
        ];
        
        $body = json_encode($bodyData);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $apiSecret ?? 'secret');
        
        // Check if API credentials are empty/default or invalid, trigger local mock page
        if (empty($apiKey) || $apiKey === 'your_api_key' || empty($apiSecret)) {
            Log::info("DompetX credentials not fully set. Using local mock simulation URL.");
            return [
                'id' => 'mock-' . uniqid(),
                'status' => 'pending',
                'amount' => $amount,
                'currency' => 'IDR',
                'payment_link' => route('dompetx.mock-checkout', [
                    'booking_id' => $bookingId, 
                    'reference' => $reference, 
                    'amount' => $amount
                ])
            ];
        }
        
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-DOMPAY-API-Key' => $apiKey,
                'X-DOMPAY-Timestamp' => $timestamp,
                'X-DOMPAY-Signature' => $signature,
                'Idempotency-Key' => $reference
            ])->post($baseUrl . '/payments/checkout', $bodyData);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('DompetX API Error response: ' . $response->body());
            throw new Exception('DompetX returned error: ' . $response->status());
        } catch (Exception $e) {
            Log::error('DompetX Request Exception: ' . $e->getMessage() . '. Falling back to local mock URL.');
            return [
                'id' => 'mock-' . uniqid(),
                'status' => 'pending',
                'amount' => $amount,
                'currency' => 'IDR',
                'payment_link' => route('dompetx.mock-checkout', [
                    'booking_id' => $bookingId, 
                    'reference' => $reference, 
                    'amount' => $amount
                ])
            ];
        }
    }
}
