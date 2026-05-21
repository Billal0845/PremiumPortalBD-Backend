<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'photo',
        'status',
        'show_on_home',
        'home_sort_order',
    ];

    protected $casts = [
        'status' => 'boolean',
        'show_on_home' => 'boolean',
        'home_sort_order' => 'integer',
    ];

    // Make sure the photo_url is appended to the JSON response
    protected $appends = ['photo_url'];

    // Accessor for the full photo URL
    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        return null;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }



    protected static function booted()
    {
        static::saved(function ($category) {
            Cache::forget('homepage_critical');
            Cache::forget('homepage_deferred');
            Cache::forget('categories_all');
            Cache::increment('shop_cache_version');
            Cache::forget('homepage_cat_' . $category->slug);
        });

        static::deleted(function ($category) {
            Cache::forget('homepage_critical');
            Cache::forget('homepage_deferred');
            Cache::forget('categories_all');
            Cache::increment('shop_cache_version');
            Cache::forget('homepage_cat_' . $category->slug);
        });
    }
}