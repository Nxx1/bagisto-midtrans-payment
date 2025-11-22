<?php

namespace Akara\MidtransPayment\Models;


use Illuminate\Database\Eloquent\Model;
use Webkul\Sales\Models\Order;

class MidtransTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'payment_type',
        'transaction_status',
        'fraud_status',
        'transaction_time',
        'expire_time',
        'status_message',
        'status_code',
        'signature_key',
        'pop_id',
        'merchant_id',
        'gross_amount',
        'currency',
        'customer_name',
        'customer_email',
        'bank',
        'va_number',
        'qr_string',
        'qr_url',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'transaction_time' => 'datetime',
        'expire_time' => 'datetime',
        'gross_amount' => 'decimal:2',
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
