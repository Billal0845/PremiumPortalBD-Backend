<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        // 1. Initialize query with items and products
        $query = Order::with(['items.product'])->latest();

        // 2. Search Filter (ID, Name, WhatsApp, Email)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('whatsapp', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        // 3. Order Status Filter
        if ($request->filled('order_status') && $request->order_status !== 'all') {
            $query->where('order_status', $request->order_status);
        }

        // 4. Payment Status Filter
        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        // 5. Date Range / Time Filter
        if ($request->filled('time_filter')) {
            switch ($request->time_filter) {
                case 'today':
                    $query->whereDate('created_at', Carbon::today());
                    break;
                case 'last_7_days':
                    $query->where('created_at', '>=', Carbon::now()->subDays(7));
                    break;
                case 'custom':
                    if ($request->filled('start_date') && $request->filled('end_date')) {
                        $query->whereBetween('created_at', [
                            Carbon::parse($request->start_date)->startOfDay(),
                            Carbon::parse($request->end_date)->endOfDay()
                        ]);
                    }
                    break;
            }
        }

        // 6. Return Paginated Results (15 per page)
        $orders = $query->paginate($request->get('per_page', 15));

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
        // Fixed validation: removed strict ENUM checks for payment_status
        $request->validate([
            'order_status' => ['required', 'string'],
            'payment_status' => ['nullable', 'string'],
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

        return DB::transaction(function () use ($order) {
            // Auto-generate subscriptions for all items
            foreach ($order->items as $item) {
                // Ensure we don't create duplicates
                if (Subscription::where('order_item_id', $item->id)->exists()) {
                    continue;
                }

                // Get package duration (default to 1 month if missing/0)
                $package = $item->productPackage;
                $durationMonths = ($package && $package->duration_months > 0) ? $package->duration_months : 1;

                Subscription::create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_package_id' => $item->product_package_id,
                    'customer_name' => $order->customer_name,
                    'whatsapp' => $order->whatsapp,
                    'customer_email' => $order->email,
                    // Automatically calculate dates
                    'start_date' => Carbon::now(),
                    'expiry_date' => Carbon::now()->addMonths($durationMonths),
                    'subscription_fee' => $item->unit_price,
                    'status' => 'active',
                    'credentials_given' => false,
                    'admin_note' => null,
                ]);
            }

            // Mark order as fulfilled
            $order->update([
                'subscription_created' => true,
                'order_status' => 'verified',
            ]);

            return response()->json([
                'message' => 'Subscriptions generated successfully',
                'order' => $order->fresh(['items', 'subscriptions']),
            ]);
        });
    }
}