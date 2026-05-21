<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class ProductPackage extends Model
{
    protected $fillable = [
        'product_id',
        'package_name',
        'duration_months',
        'price',
        'compare_price',
        'is_default',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'duration_months' => 'integer',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
        static::saved(function ($package) {
            Cache::forget('homepage_critical');
            Cache::forget('homepage_deferred');
            Cache::forget('categories_all');
            Cache::increment('shop_cache_version');

            if ($package->product) {
                Cache::forget('product_' . $package->product->slug);
            }
        });

        static::deleted(function ($package) {
            Cache::forget('homepage_critical');
            Cache::forget('homepage_deferred');
            Cache::forget('categories_all');
            Cache::increment('shop_cache_version');

            if ($package->product) {
                Cache::forget('product_' . $package->product->slug);
            }
        });
    }
}