<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Slider;


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

        // 3. Filter by Homepage Flags
        if ($request->filled('filter')) {
            if ($request->filter === 'top-selling')
                $query->where('is_top_selling', true);
            if ($request->filter === 'trending')
                $query->where('is_trending', true);
            if ($request->filter === 'new-arrivals')
                $query->where('is_new_arrival', true);
        }

        // -----------------------------------------------------
        // ADDED: Filter by Minimum and Maximum Price
        // -----------------------------------------------------
        if ($request->filled('min_price') || $request->filled('max_price')) {
            $query->whereHas('packages', function ($q) use ($request) {
                if ($request->filled('min_price')) {
                    $q->where('price', '>=', $request->min_price);
                }
                if ($request->filled('max_price')) {
                    $q->where('price', '<=', $request->max_price);
                }
            });
        }

        // 4. Sorting logic
        $sort = $request->input('sort', 'latest');
        if ($sort === 'latest') {
            $query->latest();
        } elseif ($sort === 'top_rated') {
            $query->orderBy('rating', 'desc');
        } elseif ($sort === 'price_low') {
            $query->orderBy(\App\Models\ProductPackage::select('price')
                ->whereColumn('product_id', 'products.id')
                ->where('status', true)->orderBy('is_default', 'desc')->limit(1), 'asc');
        } elseif ($sort === 'price_high') {
            $query->orderBy(\App\Models\ProductPackage::select('price')
                ->whereColumn('product_id', 'products.id')
                ->where('status', true)->orderBy('is_default', 'desc')->limit(1), 'desc');
        }

        // 5. Paginate results
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
        $sliders = Slider::where('is_active', true)->orderBy('order', 'asc')->get();

        return response()->json([
            'sliders' => $sliders,
            'top_selling' => $topSelling,
            'trending' => $trending,
            'new_arrivals' => $newArrivals,
            'categories' => $categories,
        ]);
    }

    public function show($slug)
    {
        // 1. Fetch the main product
        $product = Product::with([
            'category:id,name,slug',
            'packages' => function ($q) {
                $q->where('status', true)->orderBy('sort_order');
            }
        ])
            ->where('slug', $slug)
            ->where('status', true)
            ->firstOrFail();

        // 2. Fetch related products (same category, excluding current product)
        $relatedProducts = Product::with([
            'packages' => function ($q) {
                $q->where('status', true)->orderBy('sort_order');
            }
        ])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', true)
            ->take(5) // Limit to 5 related products
            ->get();

        // 3. Return both in the response
        return response()->json([
            'product' => $product,
            'related_products' => $relatedProducts,
        ]);
    }

}




