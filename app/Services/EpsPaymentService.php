<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class EpsPaymentService
{
  private $baseUrl;
  private $hashKey;

  public function __construct()
  {
    $this->baseUrl = config('eps.base_url');
    $this->hashKey = config('eps.hash_key');
  }

  /**
   * Generate HMAC SHA512 Hash as per EPS documentation
   */
  private function generateHash($dataToHash)
  {
    // Compute Hash using HMACSHA512 and encode in Base64
    return base64_encode(hash_hmac('sha512', $dataToHash, utf8_encode($this->hashKey), true));
  }

  /**
   * API No 01: Get Bearer Token
   */
  public function getToken()
  {
    $username = config('eps.username');
    $xHash = $this->generateHash($username);

    $response = Http::withHeaders([
      'x-hash' => $xHash,
      'Content-Type' => 'application/json'
    ])->post("{$this->baseUrl}/Auth/GetToken", [
          'userName' => $username,
          'password' => config('eps.password')
        ]);

    if ($response->successful() && isset($response['token'])) {
      return $response['token'];
    }

    throw new \Exception('Failed to generate EPS token: ' . $response->body());
  }

  /**
   * API No 02: Initialize Payment
   */
  public function initializePayment($order)
  {
    $token = $this->getToken();
    $merchantTransactionId = $order->merchant_transaction_id;
    $xHash = $this->generateHash($merchantTransactionId);

    // Required default payload values for digital goods
    $payload = [
      'merchantId' => config('eps.merchant_id'),
      'storeId' => config('eps.store_id'),
      'CustomerOrderId' => $order->order_number,
      'merchantTransactionId' => $merchantTransactionId,
      'transactionTypeId' => 1, // 1 = Web
      'totalAmount' => (float) $order->total_amount,

      // Laravel endpoints that EPS will redirect back to
      'successUrl' => url('/api/payment/success'),
      'failUrl' => url('/api/payment/fail'),
      'cancelUrl' => url('/api/payment/cancel'),

      // Customer Info
      'customerName' => $order->customer_name,
      'customerEmail' => $order->email ?? 'no-email@domain.com',
      'customerPhone' => $order->whatsapp,

      // Mandatory filler fields for digital products
      'CustomerAddress' => 'N/A',
      'CustomerCity' => 'Dhaka',
      'CustomerState' => 'Dhaka',
      'CustomerPostcode' => '1000',
      'CustomerCountry' => 'BD',
      'productName' => 'Digital Subscription',
    ];

    $response = Http::withHeaders([
      'x-hash' => $xHash,
      'Authorization' => "Bearer {$token}",
      'Content-Type' => 'application/json'
    ])->post("{$this->baseUrl}/EPSEngine/InitializeEPS", $payload);

    if ($response->successful() && isset($response['RedirectURL'])) {
      return $response['RedirectURL'];
    }

    throw new \Exception('Failed to initialize EPS payment: ' . $response->body());
  }

  /**
   * API No 03: Verify Transaction
   */
  public function verifyTransaction($merchantTransactionId)
  {
    $token = $this->getToken();
    $xHash = $this->generateHash($merchantTransactionId);

    $response = Http::withHeaders([
      'x-hash' => $xHash,
      'Authorization' => "Bearer {$token}"
    ])->get("{$this->baseUrl}/EPSEngine/CheckMerchantTransactionStatus", [
          'merchantTransactionId' => $merchantTransactionId
        ]);

    return $response->json();
  }
}