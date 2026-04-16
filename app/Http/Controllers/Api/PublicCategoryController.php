<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class PublicCategoryController extends Controller
{
    public function index()
    {
        // Added withCount('products') to get the total count
        $categories = Category::where('status', true)
            ->withCount('products')
            ->latest()
            ->get();

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