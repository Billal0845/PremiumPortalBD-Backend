<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductPackage;
use Illuminate\Http\Request;

class ProductPackageController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductPackage::with('product')->latest();

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $packages = $query->get();

        return response()->json([
            'packages' => $packages,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'package_name' => ['required', 'string', 'max:255'],
            'duration_label' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            ProductPackage::where('product_id', $request->product_id)
                ->update(['is_default' => false]);
        }

        $package = ProductPackage::create([
            'product_id' => $request->product_id,
            'package_name' => $request->package_name,
            'duration_label' => $request->duration_label,
            'price' => $request->price,
            'compare_price' => $request->compare_price,
            'is_default' => $request->boolean('is_default'),
            'sort_order' => $request->input('sort_order', 0),
            'status' => $request->has('status') ? $request->boolean('status') : true,
        ]);

        return response()->json([
            'message' => 'Product package created successfully',
            'package' => $package->load('product'),
        ], 201);
    }

    public function show(ProductPackage $productPackage)
    {
        $productPackage->load('product');

        return response()->json([
            'package' => $productPackage,
        ]);
    }

    public function update(Request $request, ProductPackage $productPackage)
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'package_name' => ['required', 'string', 'max:255'],
            'duration_label' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            ProductPackage::where('product_id', $request->product_id)
                ->where('id', '!=', $productPackage->id)
                ->update(['is_default' => false]);
        }

        $productPackage->update([
            'product_id' => $request->product_id,
            'package_name' => $request->package_name,
            'duration_label' => $request->duration_label,
            'price' => $request->price,
            'compare_price' => $request->compare_price,
            'is_default' => $request->boolean('is_default'),
            'sort_order' => $request->input('sort_order', $productPackage->sort_order),
            'status' => $request->has('status')
                ? $request->boolean('status')
                : $productPackage->status,
        ]);

        return response()->json([
            'message' => 'Product package updated successfully',
            'package' => $productPackage->fresh()->load('product'),
        ]);
    }

    public function destroy(ProductPackage $productPackage)
    {
        $productPackage->delete();

        return response()->json([
            'message' => 'Product package deleted successfully',
        ]);
    }
}