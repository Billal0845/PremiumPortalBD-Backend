<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductMinimalResource extends JsonResource
{
    public function toArray($request)
    {
        // Find the default or lowest priced package safely
        $defaultPkg = $this->packages->firstWhere('is_default', true) ?? $this->packages->first();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'featured_image' => $this->featured_image_url,
            'rating' => $this->rating ?? "5.0",
            'reviews' => $this->rating_count ?? 0,
            'price' => $defaultPkg ? $defaultPkg->price : 0,
            'comparePrice' => $defaultPkg ? $defaultPkg->compare_price : null,
            'logoText' => $this->brand ?: strtoupper(substr($this->name, 0, 2)),
        ];
    }
}