<?php

return [
  'env' => env('EPS_ENV', 'sandbox'),
  'base_url' => env('EPS_ENV') === 'live'
    ? 'https://pgapi.eps.com.bd/v1'
    : 'https://sandboxpgapi.eps.com.bd/v1',
  'merchant_id' => env('EPS_MERCHANT_ID'),
  'store_id' => env('EPS_STORE_ID'),
  'username' => env('EPS_USERNAME'),
  'password' => env('EPS_PASSWORD'),
  'hash_key' => env('EPS_HASH_KEY'),
  'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
];