<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;


class Slider extends Model
{
    use HasFactory;
    protected $fillable = ['title', 'image_path', 'is_active', 'order'];



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
