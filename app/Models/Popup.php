<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;


class Popup extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'link',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];


    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('homepage_data');
        });

        static::deleted(function () {
            Cache::forget('homepage_data');
        });
    }


}