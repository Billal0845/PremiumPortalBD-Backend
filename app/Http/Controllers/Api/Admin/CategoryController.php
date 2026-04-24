<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::latest()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })->get();


        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'status' => ['nullable', 'boolean'],
            'show_on_home' => ['nullable', 'boolean'],
            'home_sort_order' => ['nullable', 'integer'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $photoPath = null;

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('categories', 'public');
        }

        $category = Category::create([
            'name' => $request->name,
            'slug' => $request->slug ?: Str::slug($request->name),
            'status' => $request->has('status') ? $request->boolean('status') : true,
            'show_on_home' => $request->has('show_on_home') ? $request->boolean('show_on_home') : false,
            'home_sort_order' => $request->home_sort_order ?? 0,
            'photo' => $photoPath,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category,
        ], 201);
    }

    public function show(Category $category)
    {
        return response()->json([
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name,' . $category->id],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug,' . $category->id],
            'status' => ['nullable', 'boolean'],
            'show_on_home' => ['nullable', 'boolean'],
            'home_sort_order' => ['nullable', 'integer'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $photoPath = $category->photo;

        if ($request->hasFile('photo')) {
            if ($category->photo && Storage::disk('public')->exists($category->photo)) {
                Storage::disk('public')->delete($category->photo);
            }

            $photoPath = $request->file('photo')->store('categories', 'public');
        }

        $category->update([
            'name' => $request->name,
            'slug' => $request->slug ?: Str::slug($request->name),
            'status' => $request->has('status') ? $request->boolean('status') : $category->status,
            'show_on_home' => $request->has('show_on_home') ? $request->boolean('show_on_home') : $category->show_on_home,
            'home_sort_order' => $request->has('home_sort_order') ? $request->home_sort_order : $category->home_sort_order,
            'photo' => $photoPath,
        ]);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category->fresh(),
        ]);
    }

    public function destroy(Category $category)
    {
        if ($category->photo && Storage::disk('public')->exists($category->photo)) {
            Storage::disk('public')->delete($category->photo);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}