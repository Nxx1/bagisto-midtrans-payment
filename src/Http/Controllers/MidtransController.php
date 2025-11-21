<?php

namespace Akara\MidtransPayment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\OrderRepository;
use Midtrans\Config;
use Midtrans\Snap;
use Illuminate\Support\Facades\Log;
use Exception;

class MidtransController extends Controller
{
    public function token(Request $request, OrderRepository $orderRepository)
    {
        $cart = Cart::getCart();
        if (!$cart) {
            return response()->json(['error' => 'Cart not found.'], 404);
        }

        // Prepare order data (unconfirmed)
        $orderData = (new \Webkul\Sales\Transformers\OrderResource($cart))->jsonSerialize();
        $order = $orderRepository->create($orderData);

        $this->setConfig();

        $params = [
            'transaction_details' => [
                'order_id' => 'ORD-' . $order->id . '-' . time(),
                'gross_amount' => (int) round($order->grand_total),
            ],
            'customer_details' => [
                'first_name' => $order->customer_first_name ?? 'Customer',
                'email' => $order->customer_email,
                'phone' => $order->customer_phone ?? '',
            ],
            'item_details' => $this->mapItems($order),
            'callbacks' => [
                'finish' => route('shop.checkout.onepage.success'),
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);
            Log::info('Midtrans Snap token created', ['order_id' => $order->id, 'token' => $snapToken]);

            return response()->json([
                'token' => $snapToken,
                'order_id' => $order->id,
                'client_key' => config('midtrans.client_key'),
            ]);
        } catch (Exception $e) {
            Log::error('Midtrans Snap Token Error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Midtrans token failed.'], 502);
        }
    }

    public function callback(Request $request, OrderRepository $orderRepository)
    {
        $status = $request->transaction_status;
        $orderId = preg_replace('/[^0-9]/', '', $request->order_id);
        $order = $orderRepository->find($orderId);
        if (!$order)
            return response()->json(['message' => 'Order not found'], 404);

        $mappedStatus = config("midtrans.payment_status.$status", 'pending');
        $order->update(['status' => $mappedStatus]);

        Log::info('Midtrans Callback Received', ['order_id' => $orderId, 'status' => $status]);

        return response()->json(['success' => true]);
    }

    private function mapItems($order)
    {
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'id' => $item->id,
                'price' => (int) $item->price,
                'quantity' => (int) $item->quantity,
                'name' => substr($item->name, 0, 50),
            ];
        }

        if ($order->shipping_amount > 0) {
            $items[] = [
                'id' => 'shipping',
                'price' => (int) $order->shipping_amount,
                'quantity' => 1,
                'name' => 'Shipping Fee',
            ];
        }

        return $items;
    }

    private function setConfig()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.mode') === 'production';
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }
}
