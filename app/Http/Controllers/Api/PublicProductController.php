<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Slider;
use App\Models\ProductPackage;
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\ProductMinimalResource; // <--- Added Resource

class PublicProductController extends Controller
{

    // 1. Powers the Shop Page)

    public function index(Request $request)
    {
        $version = Cache::get('shop_cache_version', 1);
        $cacheKey = 'shop_products_v' . $version . '_' . md5(serialize($request->only(['category', 'search', 'filter', 'sort', 'min_price', 'max_price', 'page'])));

        $products = Cache::remember($cacheKey, 3600, function () use ($request) {
            $query = Product::with([
                'category:id,name,slug',
                'packages' => function ($q) {
                    $q->where('status', true)->orderBy('sort_order');
                }
            ])->where('status', true);

            // Filter by Category Slug
            if ($request->filled('category')) {
                $query->whereHas('category', function ($q) use ($request) {
                    $q->where('slug', $request->category);
                });
            }

            // Filter by Search Query
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('brand', 'like', '%' . $search . '%');
                });
            }

            // Filter by Homepage Flags
            if ($request->filled('filter')) {
                if ($request->filter === 'top-selling')
                    $query->where('is_top_selling', true);
                if ($request->filter === 'trending')
                    $query->where('is_trending', true);
                if ($request->filter === 'new-arrivals')
                    $query->where('is_new_arrival', true);
            }

            // Filter by Minimum and Maximum Price
            if ($request->filled('min_price') || $request->filled('max_price')) {
                $query->whereHas('packages', function ($q) use ($request) {
                    if ($request->filled('min_price'))
                        $q->where('price', '>=', $request->min_price);
                    if ($request->filled('max_price'))
                        $q->where('price', '<=', $request->max_price);
                });
            }

            // Sorting logic
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

            return $query->paginate(12);
        });

        return response()->json($products);
    }


    // ==========================================
    // 2. NEW OPTIMIZED HOMEPAGE (CRITICAL DATA)
    // ==========================================

    public function homepageCritical()
    {
        try {
            $data = Cache::remember('homepage_critical', 86400, function () {

                $topSelling = Product::select('id', 'name', 'slug', 'featured_image', 'rating', 'rating_count', 'brand')
                    ->with(['packages' => fn($q) => $q->select('id', 'product_id', 'price', 'compare_price', 'is_default')->where('status', true)->orderBy('sort_order')])
                    ->where('status', true)->where('is_top_selling', true)->latest()->take(10)->get();

                // FIX: Removed the strict select() so it doesn't crash looking for 'image_url'
                $sliders = Slider::where('is_active', true)->orderBy('order', 'asc')->get();

                $banners = Banner::select('id', 'image_url', 'target_link', 'position')->where('is_active', true)->get()->keyBy('position');

                $categories = Category::where('status', true)
                    ->withCount('products')
                    ->latest()
                    ->get();

                return [
                    'sliders' => $sliders,
                    'banners' => $banners,
                    'top_selling' => ProductMinimalResource::collection($topSelling)->resolve(),
                    'categories' => $categories,
                ];
            });

            return response()->json($data);

        } catch (\Exception $e) {
            \Log::error('Failed to load homepage critical data');
            return response()->json(['message' => 'Failed to load data. Please try again.'], 500);
        }
    }


    // ==========================================
    // 3. NEW OPTIMIZED HOMEPAGE (DEFERRED DATA)
    // ==========================================
    public function homepageDeferred()
    {
        try {
            $data = Cache::remember('homepage_deferred', 86400, function () {

                $productLoader = function ($query) {
                    $query->select('products.id', 'category_id', 'name', 'slug', 'featured_image', 'rating', 'rating_count', 'brand')
                        ->where('status', true)->latest()->take(10)
                        ->with(['packages' => fn($q) => $q->select('id', 'product_id', 'price', 'compare_price', 'is_default')->where('status', true)->orderBy('sort_order')]);
                };

                $targetSlugs = ['popular-ai-tools', 'software-licenses', 'video-editing-tools'];

                $specificCategories = Category::select('id', 'name', 'slug')
                    ->where('status', true)->whereIn('slug', $targetSlugs)
                    ->with(['products' => $productLoader])->get();

                $otherCategories = Category::select('id', 'name', 'slug')
                    ->where('status', true)->where('show_on_home', true)->whereNotIn('slug', $targetSlugs)
                    ->orderBy('home_sort_order')->with(['products' => $productLoader])->get();

                $formatCategory = fn($slug) => [
                    'category' => $specificCategories->firstWhere('slug', $slug),
                    'products' => ProductMinimalResource::collection($specificCategories->firstWhere('slug', $slug)?->products ?? [])->resolve()
                ];

                return [
                    'ai_tools' => $formatCategory('popular-ai-tools'),
                    'windows_tools' => $formatCategory('software-licenses'),
                    'video_tools' => $formatCategory('video-editing-tools'),
                    'other_categories' => $otherCategories->map(fn($cat) => [
                        'category' => $cat,
                        'products' => ProductMinimalResource::collection($cat->products)->resolve()
                    ]),
                ];
            });

            return response()->json($data);

        } catch (\Exception $e) {
            \Log::error('Failed to load homepage deferred data');
            return response()->json(['message' => 'Failed to load data. Please try again.'], 500);
        }
    }

    // ==========================================
    // 4. SHOW (KEEP EXACTLY AS IT WAS - Powers Product Details)
    // ==========================================
    public function show($slug)
    {
        $data = Cache::remember('product_' . $slug, 3600, function () use ($slug) {
            $product = Product::with([
                'category:id,name,slug',
                'packages' => function ($q) {
                    $q->where('status', true)->orderBy('sort_order');
                }
            ])
                ->where('slug', $slug)
                ->where('status', true)
                ->firstOrFail();

            $relatedProducts = Product::with([
                'packages' => function ($q) {
                    $q->where('status', true)->orderBy('sort_order');
                }
            ])
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->where('status', true)
                ->take(5)
                ->get();

            return [
                'product' => $product,
                'related_products' => $relatedProducts,
            ];
        });

        return response()->json($data);
    }
}