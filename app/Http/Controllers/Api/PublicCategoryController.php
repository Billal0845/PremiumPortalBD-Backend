<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class PublicCategoryController extends Controller
{
    public function index()
    {
        $categories = Cache::remember('categories_all', 86400, function () {
            return Category::where('status', true)
                ->withCount('products')
                ->latest()
                ->get();
        });

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function show($slug)
    {
        $category = Category::where('slug', $slug)
            ->where('status', true)
            ->firstOrFail();

        return response()->json([
            'category' => $category,
        ]);
    }
}