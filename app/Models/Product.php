<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage; // Added
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'brand',
        'short_description',
        'quick_view',
        'description',
        'specification',
        'featured_image',
        'requires_email',
        'status',
        'rating',
        'rating_count',
        'is_top_selling',
        'is_trending',
        'is_new_arrival',
    ];

    protected $casts = [
        'requires_email' => 'boolean',
        'status' => 'boolean',
        'is_top_selling' => 'boolean',
        'is_trending' => 'boolean',
        'is_new_arrival' => 'boolean',
    ];

    // This makes sure the URL is included when the model is converted to JSON
    protected $appends = ['featured_image_url'];

    public function getFeaturedImageUrlAttribute()
    {
        if ($this->featured_image) {
            // Returns the full URL (e.g., http://localhost:8000/storage/products/xyz.jpg)
            return asset('storage/' . $this->featured_image);
        }
        return null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ProductPackage::class)->orderBy('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    protected static function booted()
    {
        static::saved(function ($product) {
            Cache::forget('homepage_data');
            Cache::forget('product_' . $product->slug); // Clears the single product page cache too
        });

        static::deleted(function ($product) {
            Cache::forget('homepage_data');
            Cache::forget('product_' . $product->slug);
        });
    }


}