<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        return response()->json([
            'banners' => Banner::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'target_link' => 'nullable|string|max:255',
            'position' => 'required|string|unique:banners,position',
            'is_active' => 'boolean',
        ]);

        $imagePath = $request->file('image')->store('banners', 'public');

        $banner = Banner::create([
            'title' => $request->title,
            'image_url' => $imagePath,
            'target_link' => $request->target_link,
            'position' => $request->position,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return response()->json(['message' => 'Banner created', 'banner' => $banner], 201);
    }

    public function destroy(Banner $banner)
    {
        if (Storage::disk('public')->exists($banner->image_url)) {
            Storage::disk('public')->delete($banner->image_url);
        }
        $banner->delete();
        return response()->json(['message' => 'Banner deleted']);
    }
}