<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Popup;
use Illuminate\Support\Facades\Cache;

class PublicPopupController extends Controller
{
    public function getActivePopup()
    {
        $popup = Cache::remember('active_popup', 86400, function () {
            return Popup::where('is_active', true)->first();
        });

        if (!$popup) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $popup]);
    }
}