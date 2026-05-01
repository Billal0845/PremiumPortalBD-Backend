<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SliderController extends Controller
{
    public function index()
    {
        return response()->json(Slider::orderBy('order', 'asc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'title' => 'nullable|string|max:255',
        ]);

        $path = $request->file('image')->store('sliders', 'public');

        // Put the new slider at the end of the list
        $maxOrder = Slider::max('order') ?? 0;

        $slider = Slider::create([
            'title' => $request->title,
            'image_path' => $path,
            'is_active' => $request->is_active === 'true' || $request->is_active === true,
            'order' => $maxOrder + 1, // <-- Assign highest order
        ]);

        return response()->json($slider, 201);
    }

    // Add this new method for reordering
    public function updateOrder(Request $request)
    {
        $request->validate([
            'sliders' => 'required|array',
            'sliders.*.id' => 'required|exists:sliders,id',
            'sliders.*.order' => 'required|integer',
        ]);

        foreach ($request->sliders as $item) {
            Slider::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }

    public function destroy(Slider $slider)
    {
        if (Storage::disk('public')->exists($slider->image_path)) {
            Storage::disk('public')->delete($slider->image_path);
        }
        $slider->delete();
        return response()->json(['message' => 'Slider deleted successfully']);
    }
}
