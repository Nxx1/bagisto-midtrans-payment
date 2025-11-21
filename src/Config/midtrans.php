<?php

return [
    'mode' => env('MIDTRANS_MODE', 'sandbox'),
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'payment_status' => [
        'settlement' => 'completed',
        'pending' => 'pending',
        'deny' => 'canceled',
        'expire' => 'canceled',
        'cancel' => 'canceled',
    ],
];
