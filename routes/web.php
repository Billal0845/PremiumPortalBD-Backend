<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});



Route::get('/clear-my-cache', function () {
    Artisan::call('cache:clear');
    return 'Cache cleared successfully! Homepage will now show new updates.';
});



