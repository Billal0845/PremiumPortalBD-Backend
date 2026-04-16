<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_id',
        'product_package_id',
        'customer_name',
        'whatsapp',
        'customer_email',
        'start_date',
        'expiry_date',
        'subscription_fee',
        'status',
        'credentials_given',
        'admin_note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expiry_date' => 'date',
        'subscription_fee' => 'decimal:2',
        'credentials_given' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productPackage(): BelongsTo
    {
        return $this->belongsTo(ProductPackage::class);
    }
}