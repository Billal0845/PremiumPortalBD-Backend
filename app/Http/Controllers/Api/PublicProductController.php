<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class PublicProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with([
            'category:id,name,slug',
            'packages' => function ($q) {
                $q->where('status', true)->orderBy('sort_order');
            }
        ])
            ->where('status', true);

        // optional category filter
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // optional search
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('brand', 'like', '%' . $search . '%');
            });
        }

        $products = $query->latest()->get();

        return response()->json([
            'products' => $products,
        ]);
    }

    public function show($slug)
    {
        $product = Product::with([
            'category:id,name,slug',
            'packages' => function ($q) {
                $q->where('status', true)->orderBy('sort_order');
            }
        ])
            ->where('slug', $slug)
            ->where('status', true)
            ->firstOrFail();

        return response()->json([
            'product' => $product,
        ]);
    }

}




