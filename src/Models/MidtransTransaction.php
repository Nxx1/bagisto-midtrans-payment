<?php

namespace Akara\MidtransPayment\Models;


use Illuminate\Database\Eloquent\Model;
use Webkul\Sales\Models\Order;

class MidtransTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'midtrans_order_id',
        'payment_type',
        'bank',
        'va_number',
        'qr_string',
        'qr_url',
        'transaction_status',
        'fraud_status',
        'raw_response',
        'expire_time'
    ];

    protected $casts = [
        'raw_response' => 'array',
        'expire_time'  => 'datetime'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function notifications()
    {
        return $this->hasMany(MidtransNotification::class);
    }
}
