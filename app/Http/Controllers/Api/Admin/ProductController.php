<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')
            ->latest()
            ->get();

        return response()->json([
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255', 'unique:products,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'brand' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string'],
            'quick_view' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'specification' => ['nullable', 'string'],
            'requires_email' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'rating_count' => ['nullable', 'integer', 'min:0'],
        ]);

        $featuredImagePath = null;

        if ($request->hasFile('featured_image')) {
            $featuredImagePath = $request->file('featured_image')->store('products', 'public');
        }

        $product = Product::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'slug' => $request->slug ?: Str::slug($request->name),
            'brand' => $request->brand,
            'short_description' => $request->short_description,
            'quick_view' => $request->quick_view,
            'description' => $request->description,
            'specification' => $request->specification,
            'featured_image' => $featuredImagePath,
            'requires_email' => $request->has('requires_email') ? $request->boolean('requires_email') : false,
            'status' => $request->has('status') ? $request->boolean('status') : true,
            'rating' => $request->rating ?? 0,
            'rating_count' => $request->rating_count ?? 0,
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load('category'),
        ], 201);
    }

    public function show(Product $product)
    {
        $product->load('category', 'packages');

        return response()->json([
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255', 'unique:products,name,' . $product->id],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug,' . $product->id],
            'brand' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string'],
            'quick_view' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'specification' => ['nullable', 'string'],
            'requires_email' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'rating_count' => ['nullable', 'integer', 'min:0'],
        ]);

        $featuredImagePath = $product->featured_image;

        if ($request->hasFile('featured_image')) {
            if ($product->featured_image && Storage::disk('public')->exists($product->featured_image)) {
                Storage::disk('public')->delete($product->featured_image);
            }

            $featuredImagePath = $request->file('featured_image')->store('products', 'public');
        }

        $product->update([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'slug' => $request->slug ?: Str::slug($request->name),
            'brand' => $request->brand,
            'short_description' => $request->short_description,
            'quick_view' => $request->quick_view,
            'description' => $request->description,
            'specification' => $request->specification,
            'featured_image' => $featuredImagePath,
            'rating' => $request->rating ?? 0,
            'rating_count' => $request->rating_count ?? 0,
            'requires_email' => $request->has('requires_email')
                ? $request->boolean('requires_email')
                : $product->requires_email,
            'status' => $request->has('status')
                ? $request->boolean('status')
                : $product->status,
        ]);

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->fresh()->load('category', 'packages'),
        ]);
    }

    public function destroy(Product $product)
    {
        if ($product->featured_image && Storage::disk('public')->exists($product->featured_image)) {
            Storage::disk('public')->delete($product->featured_image);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}