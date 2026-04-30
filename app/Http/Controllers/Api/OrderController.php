<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPackage;
use App\Services\EpsPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class OrderController extends Controller
{
    protected $epsService;

    public function __construct(EpsPaymentService $epsService)
    {
        $this->epsService = $epsService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_package_id' => ['required', 'exists:product_packages,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $paymentUrl = DB::transaction(function () use ($request) {
                $orderNumber = $this->generateOrderNumber();
                $merchantTransactionId = date('YmdHis') . rand(1000, 9999);

                $order = Order::create([
                    'order_number' => $orderNumber,
                    'merchant_transaction_id' => $merchantTransactionId,
                    'customer_name' => $request->customer_name,
                    'whatsapp' => $request->whatsapp,
                    'email' => $request->email,
                    'total_amount' => 0,
                    'event_id' => $request->event_id, //facebook pixel
                    'order_status' => 'pending',
                    'payment_status' => 'pending',
                    'notes' => $request->notes,
                    'subscription_created' => false,
                ]);

                $totalAmount = 0;

                foreach ($request->items as $item) {
                    $product = Product::where('id', $item['product_id'])->where('status', true)->firstOrFail();
                    $package = ProductPackage::where('id', $item['product_package_id'])->where('product_id', $product->id)->firstOrFail();

                    $quantity = (int) $item['quantity'];
                    $subtotal = (float) $package->price * $quantity;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_package_id' => $package->id,
                        'product_name' => $product->name,
                        'package_name' => $package->package_name,
                        'unit_price' => $package->price,
                        'quantity' => $quantity,
                        'subtotal' => $subtotal,
                    ]);

                    $totalAmount += $subtotal;
                }

                $order->update(['total_amount' => $totalAmount]);

                // Call EPS Service to get Payment Redirect URL
                return $this->epsService->initializePayment($order);
            });

            return response()->json([
                'message' => 'Order initialized successfully',
                'payment_url' => $paymentUrl
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Payment initialization failed', 'error' => $e->getMessage()], 500);
        }
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'PPBD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $orderNumber)->exists());
        return $orderNumber;
    }




    // --- EPS CALLBACK METHODS ---

    public function paymentSuccess(Request $request)
    {
        Log::info('--- EPS SUCCESS CALLBACK HIT ---');
        Log::info('Callback URL Params: ', $request->all());

        // EPS actually sends 'MerchantTransactionId' with a capital 'M'
        $merchantTransactionId = $request->input('MerchantTransactionId')
            ?? $request->input('merchantTransactionId')
            ?? $request->input('data');

        if (!$merchantTransactionId) {
            Log::error('EPS Callback Error: No transaction ID found in URL.');
            return redirect()->to(config('eps.frontend_url') . '/checkout/status?status=failed');
        }

        $order = Order::where('merchant_transaction_id', $merchantTransactionId)->first();

        if (!$order) {
            Log::error('EPS Callback Error: Order not found for ID: ' . $merchantTransactionId);
            return redirect()->to(config('eps.frontend_url') . '/checkout/status?status=failed');
        }

        try {
            // Verify the transaction strictly with EPS API
            $verifyResponse = $this->epsService->verifyTransaction($merchantTransactionId);
            Log::info('EPS Verification API Response: ', $verifyResponse ?? []);

            $status = $verifyResponse['Status'] ?? $verifyResponse['status'] ?? '';

            if (strtoupper($status) === 'SUCCESS') {
                $order->update([
                    'payment_status' => 'completed', // <--- FIXED: Matches your database ENUM exactly

                    // The log showed 'EPSTransactionId' is used in the verify response
                    'eps_transaction_id' => $verifyResponse['EPSTransactionId'] ?? $merchantTransactionId
                ]);

                // Notify Admin
                AdminNotification::create([
                    'type' => 'new_order',
                    'title' => 'New Order Paid (EPS)',
                    'message' => 'Order #' . $order->order_number . ' payment successful.',
                    'channel' => 'dashboard',
                    'related_order_id' => $order->id,
                ]);


                // --- NEW: FACEBOOK CONVERSIONS API ---
                try {
                    $pixelId = env('FB_PIXEL_ID');
                    $accessToken = env('FB_CAPI_TOKEN');

                    if ($pixelId && $accessToken && $order->event_id) {
                        Http::post("https://graph.facebook.com/v18.0/{$pixelId}/events", [
                            'access_token' => $accessToken,
                            'data' => [
                                [
                                    'event_name' => 'Purchase',
                                    'event_time' => time(),
                                    'action_source' => 'website',
                                    'event_id' => $order->event_id, // Matches frontend Pixel!
                                    'user_data' => [
                                        // Hashed user info (Meta requires SHA256)
                                        'em' => $order->email ? hash('sha256', strtolower(trim($order->email))) : null,
                                        'ph' => hash('sha256', '+88' . $order->whatsapp), // Assuming BD number format
                                        'client_ip_address' => $request->ip(),
                                        'client_user_agent' => $request->userAgent(),
                                    ],
                                    'custom_data' => [
                                        'currency' => 'BDT',
                                        'value' => (float) $order->total_amount,
                                    ],
                                ]
                            ]
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('FB CAPI Error: ' . $e->getMessage());
                }
                // --- END CAPI ---


                return redirect()->to(config('eps.frontend_url') . '/checkout/status?status=success&order=' . $order->order_number);
            } else {
                Log::error('EPS Callback Error: Verification Status was not SUCCESS.', $verifyResponse ?? []);
            }
        } catch (\Exception $e) {
            Log::error('EPS Callback Error: Exception during verification - ' . $e->getMessage());
        }



        return redirect()->to(config('eps.frontend_url') . '/checkout/status?status=failed');
    }

    public function paymentFail(Request $request)
    {
        Log::info('--- EPS FAIL CALLBACK HIT ---', $request->all());
        return redirect()->to(config('eps.frontend_url') . '/checkout/status?status=failed');
    }

    public function paymentCancel(Request $request)
    {
        Log::info('--- EPS CANCEL CALLBACK HIT ---', $request->all());
        return redirect()->to(config('eps.frontend_url') . '/checkout/status?status=cancelled');
    }
}