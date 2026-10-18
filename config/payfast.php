<?php

return [
    'mode' => env('PAYFAST_MODE', 'sandbox'),
    'merchant_id' => env('PAYFAST_MERCHANT_ID'),
    'merchant_key' => env('PAYFAST_MERCHANT_KEY'),
    'passphrase' => env('PAYFAST_PASSPHRASE'),
    'return_url' => env('PAYFAST_RETURN_URL'),
    'cancel_url' => env('PAYFAST_CANCEL_URL'),
    'notify_url' => env('PAYFAST_NOTIFY_URL'),
    'routes' => true,
];
