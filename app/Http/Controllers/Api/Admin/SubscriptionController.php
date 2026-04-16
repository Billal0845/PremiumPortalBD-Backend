<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscription::with([
            'order',
            'orderItem',
            'product',
            'productPackage',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('expiring_soon') && $request->boolean('expiring_soon')) {
            $query->whereDate('expiry_date', '>=', now()->toDateString())
                ->whereDate('expiry_date', '<=', now()->addDays(30)->toDateString());
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('whatsapp', 'like', '%' . $search . '%')
                    ->orWhere('customer_email', 'like', '%' . $search . '%');
            });
        }

        $subscriptions = $query->paginate(20);

        return response()->json($subscriptions);
    }

    public function show(Subscription $subscription)
    {
        $subscription->load([
            'order',
            'orderItem',
            'product',
            'productPackage',
        ]);

        return response()->json([
            'subscription' => $subscription,
        ]);
    }

    public function update(Request $request, Subscription $subscription)
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date'],
            'status' => ['required', 'in:active,expired,suspended'],
            'admin_note' => ['nullable', 'string'],
            'credentials_given' => ['nullable', 'boolean'],
        ]);

        if ($request->expiry_date < $request->start_date) {
            return response()->json([
                'message' => 'Expiry date must be after or equal to start date.'
            ], 422);
        }

        $subscription->update([
            'start_date' => $request->start_date,
            'expiry_date' => $request->expiry_date,
            'status' => $request->status,
            'admin_note' => $request->admin_note,
            'credentials_given' => $request->boolean('credentials_given'),
        ]);

        return response()->json([
            'message' => 'Subscription updated successfully',
            'subscription' => $subscription->fresh([
                'order',
                'orderItem',
                'product',
                'productPackage',
            ]),
        ]);
    }

    public function updateStatus(Request $request, Subscription $subscription)
    {
        $request->validate([
            'status' => ['required', 'in:active,expired,suspended'],
        ]);

        $subscription->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Subscription status updated successfully',
            'subscription' => $subscription,
        ]);
    }
}