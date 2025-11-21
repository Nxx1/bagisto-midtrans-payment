<?php

return [
    'midtrans' => [
        'code' => 'midtrans',
        'title' => 'midtrans',
        'description' => 'Pay securely via Midtrans',
        'class' => \Akara\MidtransPayment\Payment\MidtransPayment::class,
        'active' => true,
        'sort' => 1,
        'image' => null,
    ],
];
