<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
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
        ])->where('status', true);

        // 1. Filter by Category Slug
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // 2. Filter by Search Query
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('brand', 'like', '%' . $search . '%');
            });
        }

        // 3. Filter by Homepage Flags (e.g. View All Top Selling)
        if ($request->filled('filter')) {
            if ($request->filter === 'top-selling')
                $query->where('is_top_selling', true);
            if ($request->filter === 'trending')
                $query->where('is_trending', true);
            if ($request->filter === 'new-arrivals')
                $query->where('is_new_arrival', true);
        }

        // 4. Sorting logic
        $sort = $request->input('sort', 'latest');
        if ($sort === 'latest') {
            $query->latest();
        } elseif ($sort === 'top_rated') {
            $query->orderBy('rating', 'desc');
        } elseif ($sort === 'price_low') {
            // Sort by the default package price (Ascending)
            $query->orderBy(\App\Models\ProductPackage::select('price')
                ->whereColumn('product_id', 'products.id')
                ->where('status', true)->orderBy('is_default', 'desc')->limit(1), 'asc');
        } elseif ($sort === 'price_high') {
            // Sort by the default package price (Descending)
            $query->orderBy(\App\Models\ProductPackage::select('price')
                ->whereColumn('product_id', 'products.id')
                ->where('status', true)->orderBy('is_default', 'desc')->limit(1), 'desc');
        }

        // 5. Paginate results (12 products per page)
        $products = $query->paginate(12);

        return response()->json($products);
    }


    public function homepageData()
    {
        // 1. Top Selling (Max 15)
        $topSelling = Product::with(['packages' => fn($q) => $q->where('status', true)->orderBy('sort_order')])
            ->where('status', true)->where('is_top_selling', true)->latest()->take(15)->get();



        // 2. Trending (Max 10)
        $trending = Product::with(['packages' => fn($q) => $q->where('status', true)->orderBy('sort_order')])
            ->where('status', true)->where('is_trending', true)->latest()->take(10)->get();

        // 3. New Arrivals (Max 10)
        $newArrivals = Product::with(['packages' => fn($q) => $q->where('status', true)->orderBy('sort_order')])
            ->where('status', true)->where('is_new_arrival', true)->latest()->take(10)->get();

        // 4. Categories admin chose to show on home (Max 10 products per category)

        $categories = Category::where('status', true)
            ->where('show_on_home', true)
            ->orderBy('home_sort_order')
            ->with([
                'products' => function ($query) {
                    $query->where('status', true)
                        ->latest()
                        ->take(10)
                        ->with(['packages' => fn($q) => $q->where('status', true)->orderBy('sort_order')]);
                }
            ])
            ->get();

        return response()->json([
            'top_selling' => $topSelling,
            'trending' => $trending,
            'new_arrivals' => $newArrivals,
            'categories' => $categories,
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




