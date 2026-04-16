<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'method_name',
        'account_type',
        'account_number',
        'account_name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
}