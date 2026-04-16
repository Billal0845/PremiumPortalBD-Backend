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

        // Total Sales
        $totalSales = Order::where('order_status', 'verified')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        // Total Orders
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Active Subscriptions
        $activeSubscriptions = Subscription::where('status', 'active')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Expiring Soon (next 7 days)
        $expiringSoon = Subscription::where('status', 'active')
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->whereDate('expiry_date', '<=', now()->addDays(7)->toDateString())
            ->count();

        return response()->json([
            'kpis' => [
                'total_sales' => (float) $totalSales,
                'total_orders' => $totalOrders,
                'active_subscriptions' => $activeSubscriptions,
                'expiring_soon' => $expiringSoon,
            ],
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

        if ($request->filled('start_date') && $request->filled('end_date')) {
            return [
                \Carbon\Carbon::parse($request->start_date)->startOfDay(),
                \Carbon\Carbon::parse($request->end_date)->endOfDay(),
            ];
        }

        // default → this month
        return [now()->startOfMonth(), now()->endOfDay()];
    }
}