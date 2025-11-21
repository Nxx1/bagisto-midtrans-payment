<?php

namespace Akara\MidtransPayment\Http\Controllers;

use Akara\MidtransPayment\Models\MidtransNotification;

class MidtransNotificationController extends Controller
{
    public function handle(Request $request)
    {
        $notif = new \Midtrans\Notification();

        $tx = MidtransTransaction::where('midtrans_order_id', $notif->order_id)->first();
        if (!$tx)
            return response('Not found', 404);

        MidtransNotification::create([
            'midtrans_transaction_id' => $tx->id,
            'payload' => (array) $notif,
        ]);

        $tx->update([
            'transaction_status' => $notif->transaction_status,
            'fraud_status' => $notif->fraud_status,
        ]);

        // update Bagisto order status based on Midtrans logic

        return response()->json(['ok' => true]);
    }
}
