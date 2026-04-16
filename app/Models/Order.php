<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_name',
        'whatsapp',
        'email',
        'total_amount',
        'order_status',
        'payment_status',
        'screenshot_path',
        'notes',
        'subscription_created',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'subscription_created' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function adminNotifications(): HasMany
    {
        return $this->hasMany(AdminNotification::class, 'related_order_id');
    }
}