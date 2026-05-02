<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Slider;
use App\Models\ProductPackage;
use Illuminate\Support\Facades\Cache; // Make sure this is imported!

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
            $query->orderBy(ProductPackage::select('price')
                ->whereColumn('product_id', 'products.id')
                ->where('status', true)->orderBy('is_default', 'desc')->limit(1), 'asc');
        } elseif ($sort === 'price_high') {
            $query->orderBy(ProductPackage::select('price')
                ->whereColumn('product_id', 'products.id')
                ->where('status', true)->orderBy('is_default', 'desc')->limit(1), 'desc');
        }

        // 5. Paginate results
        $products = $query->paginate(12);

        return response()->json($products);
    }


    public function homepageData()
    {
        // Cache the homepage data for 3600 seconds (1 hour)
        $data = Cache::remember('homepage_data', 3600, function () {

            $topSelling = Product::with(['packages' => fn($q) => $q->where('status', true)->orderBy('sort_order')])
                ->where('status', true)->where('is_top_selling', true)->latest()->take(15)->get();

            $trending = Product::with(['packages' => fn($q) => $q->where('status', true)->orderBy('sort_order')])
                ->where('status', true)->where('is_trending', true)->latest()->take(10)->get();

            $newArrivals = Product::with(['packages' => fn($q) => $q->where('status', true)->orderBy('sort_order')])
                ->where('status', true)->where('is_new_arrival', true)->latest()->take(10)->get();

            $sliders = Slider::where('is_active', true)->orderBy('order', 'asc')->get();

            // 1. Fetch Banners
            $banners = Banner::where('is_active', true)->get()->keyBy('position');

            // 2. Fetch all active home categories
            $allCategories = Category::where('status', true)
                ->where('show_on_home', true)
                ->orderBy('home_sort_order')
                ->with([
                    'products' => function ($query) {
                        $query->where('status', true)
                            ->latest()
                            ->take(10)
                            ->with(['packages' => fn($q) => $q->where('status', true)->orderBy('sort_order')]);
                    }
                ])->get();

            // 3. Extract Specific Categories based on your slugs
            $aiToolsCategory = $allCategories->firstWhere('slug', 'popular-ai-tools');
            $windowsCategory = $allCategories->firstWhere('slug', 'software-licenses');
            $videoCategory = $allCategories->firstWhere('slug', 'video-editing-tools');

            // 4. Filter out the specific ones to keep "Other Categories"
            $otherCategories = $allCategories->filter(function ($cat) {
                return !in_array($cat->slug, ['popular-ai-tools', 'software-licenses', 'video-editing-tools']);
            })->values();

            // Return the array of data that needs to be cached
            return [
                'sliders' => $sliders,
                'top_selling' => $topSelling,
                'trending' => $trending,
                'new_arrivals' => $newArrivals,
                'banners' => $banners,
                // Separated Categories:
                'ai_tools' => $aiToolsCategory,
                'windows_tools' => $windowsCategory,
                'video_tools' => $videoCategory,
                'other_categories' => $otherCategories,
            ];
        });

        // Return the cached data
        return response()->json($data);
    }



    public function show($slug)
    {
        // Cache individual product pages for 3600 seconds (1 hour). 
        // We use the slug in the cache key so every product has its own cache file.
        $data = Cache::remember('product_' . $slug, 3600, function () use ($slug) {

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

            // 3. Return the array of data that needs to be cached
            return [
                'product' => $product,
                'related_products' => $relatedProducts,
            ];
        });

        // Return the cached data
        return response()->json($data);
    }
}