<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Popup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PopupController extends Controller
{
    public function index()
    {
        $popups = Popup::latest()->get();
        return response()->json($popups);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'link' => 'nullable|url',
            'is_active' => 'boolean'
        ]);

        $data = $request->only(['link', 'is_active']);

        // Handle Image Upload
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('popups', 'public');
        }

        // If this one is set to active, deactivate all others
        if (!empty($data['is_active']) && $data['is_active'] == true) {
            Popup::where('is_active', true)->update(['is_active' => false]);
        }

        $popup = Popup::create($data);

        return response()->json([
            'message' => 'Popup created successfully',
            'data' => $popup
        ], 201);
    }

    // Toggle active status easily from a table toggle button
    public function updateStatus(Request $request, Popup $popup)
    {
        $request->validate([
            'is_active' => 'required|boolean'
        ]);

        // If setting this one to active, deactivate all others first
        if ($request->is_active) {
            Popup::where('id', '!=', $popup->id)->update(['is_active' => false]);
        }

        $popup->update(['is_active' => $request->is_active]);

        return response()->json([
            'message' => 'Popup status updated successfully',
            'data' => $popup
        ]);
    }

    public function destroy(Popup $popup)
    {
        // Delete image from storage
        if ($popup->image) {
            Storage::disk('public')->delete($popup->image);
        }

        $popup->delete();

        return response()->json([
            'message' => 'Popup deleted successfully'
        ]);
    }
}