<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        // 1. Total Revenue: Total price of the paid order in that date range
        $totalSales = Order::where('payment_status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        // 2. Total Orders: Number of orders in that date range
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // 3. Active Subs: Global count of active subscriptions (not bound by date range)
        $activeSubscriptions = Subscription::where('status', 'active')->count();

        // 4. Expiring Soon: Active subscriptions expiring in the next 7 days
        $expiringSoon = Subscription::where('status', 'active')
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->whereDate('expiry_date', '<=', now()->addDays(7)->toDateString())
            ->count();

        // Get Recent Orders
        // Removed `with('user')` because your Order model uses `customer_name` directly
        $recentOrders = Order::latest()
            ->take(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->order_number ?? '#ORD-' . $order->id,
                    'customer' => $order->customer_name ?? 'Guest',
                    'product' => 'Subscription/Package',
                    'amount' => '৳ ' . number_format($order->total_amount, 2),
                    'status' => $order->order_status,
                    'date' => $order->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'kpis' => [
                'total_sales' => (float) $totalSales,
                'total_orders' => $totalOrders,
                'active_subscriptions' => $activeSubscriptions,
                'expiring_soon' => $expiringSoon,
            ],
            'recent_orders' => $recentOrders,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }

    private function resolveDateRange(Request $request): array
    {
        $filter = $request->get('filter');

        if ($filter === 'today') {
            return [now()->startOfDay(), now()->endOfDay()];
        }

        if ($filter === 'last_week') {
            return [now()->subDays(7)->startOfDay(), now()->endOfDay()];
        }

        if ($filter === 'this_month') {
            return [now()->startOfMonth(), now()->endOfDay()];
        }

        // Handle Custom Dates
        if ($filter === 'custom' && $request->filled('start_date') && $request->filled('end_date')) {
            return [
                \Carbon\Carbon::parse($request->start_date)->startOfDay(),
                \Carbon\Carbon::parse($request->end_date)->endOfDay(),
            ];
        }

        return [now()->startOfMonth(), now()->endOfDay()];
    }
}