<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'screenshot' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_package_id' => ['required', 'exists:product_packages,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($request) {
            $screenshotPath = $request->file('screenshot')->store('orders', 'public');

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_name' => $request->customer_name,
                'whatsapp' => $request->whatsapp,
                'email' => $request->email,
                'total_amount' => 0,
                'order_status' => 'pending',
                'payment_status' => 'pending',
                'screenshot_path' => $screenshotPath,
                'notes' => $request->notes,
                'subscription_created' => false,
            ]);

            $totalAmount = 0;

            foreach ($request->items as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->where('status', true)
                    ->firstOrFail();

                $package = ProductPackage::where('id', $item['product_package_id'])
                    ->where('product_id', $product->id)
                    ->where('status', true)
                    ->firstOrFail();

                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $package->price;
                $subtotal = $unitPrice * $quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_package_id' => $package->id,
                    'product_name' => $product->name,
                    'package_name' => $package->package_name,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ]);

                $totalAmount += $subtotal;
            }

            $order->update([
                'total_amount' => $totalAmount,
            ]);

            AdminNotification::create([
                'type' => 'new_order',
                'title' => 'New order received',
                'message' => 'Order #' . $order->order_number . ' has been placed.',
                'channel' => 'dashboard',
                'is_read' => false,
                'related_order_id' => $order->id,
            ]);

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => $order->load('items'),
            ], 201);
        });
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'PPBD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}