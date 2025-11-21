<?php

namespace Akara\MidtransPayment\Models;

use Illuminate\Database\Eloquent\Model;

class MidtransNotification extends Model
{
    protected $fillable = [
        'midtrans_transaction_id',
        'payload'
    ];

    protected $casts = [
        'payload' => 'array'
    ];
}
