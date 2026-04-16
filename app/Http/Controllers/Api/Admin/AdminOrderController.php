<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('items')->latest();

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->order_status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('whatsapp', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $orders = $query->paginate(20);

        return response()->json($orders);
    }

    public function show(Order $order)
    {
        $order->load([
            'items.product',
            'items.productPackage',
            'subscriptions',
            'adminNotifications',
        ]);

        return response()->json([
            'order' => $order,
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'order_status' => ['required', 'in:pending,verified,cancelled'],
            'payment_status' => ['nullable', 'in:pending,verified'],
            'notes' => ['nullable', 'string'],
        ]);

        $order->update([
            'order_status' => $request->order_status,
            'payment_status' => $request->payment_status ?? $order->payment_status,
            'notes' => $request->notes ?? $order->notes,
        ]);

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order->fresh('items'),
        ]);
    }

    public function createSubscriptions(Request $request, Order $order)
    {
        if ($order->subscription_created) {
            return response()->json([
                'message' => 'Subscriptions already created for this order.',
            ], 422);
        }

        if ($order->order_status === 'cancelled') {
            return response()->json([
                'message' => 'Cannot create subscriptions for a cancelled order.',
            ], 422);
        }

        $request->validate([
            'subscriptions' => ['required', 'array', 'min:1'],
            'subscriptions.*.order_item_id' => ['required', 'exists:order_items,id'],
            'subscriptions.*.start_date' => ['required', 'date'],
            'subscriptions.*.expiry_date' => ['required', 'date'],
            'subscriptions.*.admin_note' => ['nullable', 'string'],
            'subscriptions.*.credentials_given' => ['nullable', 'boolean'],
            'subscriptions.*.status' => ['nullable', 'in:active,expired,suspended'],
        ]);

        return DB::transaction(function () use ($request, $order) {
            foreach ($request->subscriptions as $subData) {
                $orderItem = $order->items()->with(['product', 'productPackage'])->find($subData['order_item_id']);

                if (!$orderItem) {
                    return response()->json([
                        'message' => 'Invalid order item for this order.',
                    ], 422);
                }

                if (Subscription::where('order_item_id', $orderItem->id)->exists()) {
                    continue;
                }

                if ($subData['expiry_date'] < $subData['start_date']) {
                    return response()->json([
                        'message' => 'Expiry date must be after or equal to start date.',
                    ], 422);
                }

                Subscription::create([
                    'order_id' => $order->id,
                    'order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'product_package_id' => $orderItem->product_package_id,
                    'customer_name' => $order->customer_name,
                    'whatsapp' => $order->whatsapp,
                    'customer_email' => $order->email,
                    'start_date' => $subData['start_date'],
                    'expiry_date' => $subData['expiry_date'],
                    'subscription_fee' => $orderItem->unit_price,
                    'status' => $subData['status'] ?? 'active',
                    'credentials_given' => $subData['credentials_given'] ?? false,
                    'admin_note' => $subData['admin_note'] ?? null,
                ]);
            }

            $order->update([
                'subscription_created' => true,
                'order_status' => 'verified',
                'payment_status' => 'verified',
            ]);

            return response()->json([
                'message' => 'Subscriptions created successfully',
                'order' => $order->fresh(['items', 'subscriptions']),
            ]);
        });
    }
}