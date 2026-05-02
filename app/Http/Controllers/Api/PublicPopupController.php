<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Popup;

class PublicPopupController extends Controller
{
    public function getActivePopup()
    {
        // Fetch only the active popup
        $popup = Popup::where('is_active', true)->first();

        if (!$popup) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $popup]);
    }
}