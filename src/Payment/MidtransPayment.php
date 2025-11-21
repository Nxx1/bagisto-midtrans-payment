<?php

namespace Akara\MidtransPayment\Payment;

use Akara\MidtransPayment\Models\MidtransTransaction;
use Webkul\Checkout\Facades\Cart;
use Webkul\Payment\Payment\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Checkout\Repositories\CartRepository;
use Midtrans\Config;
use Midtrans\Snap;
use Throwable;
use Exception;

class MidtransPayment extends Payment
{
    protected $code = 'midtrans';

    public function __construct(
        protected OrderRepository $orderRepository,
        protected InvoiceRepository $invoiceRepository,
        protected CartRepository $cartRepository
    ) {
    }

    /**
     * Generate redirect URL for Midtrans Snap Checkout.
     */
    public function getRedirectUrl(): string
    {
        $cart = $this->getCart();

        if (!$cart || !$cart->id) {
            abort(400, 'Cart not found.');
        }

        // Convert cart to order
        $data = (new OrderResource($cart))->jsonSerialize();
        $order = $this->orderRepository->create($data);

        // Midtrans Configuration
        Config::$serverKey = $this->getServerKey();
        Config::$isProduction = $this->getConfigData('mode') === 'production';
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $orderIdMidtrans = 'order-' . $order->id . '-' . time();

        $amount = round($order->grand_total, 2);
        if ($amount <= 0) {
            abort(400, 'Invalid order total.');
        }

        $params = [
            'transaction_details' => [
                'order_id' => $orderIdMidtrans,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $order->customer_first_name ?? null,
                'email' => $order->customer_email ?? null,
                'phone' => $order->customer_phone ?? null,
            ],
            'item_details' => $this->mapOrderItems($order)
        ];

        try {
            $response = Snap::createTransaction($params)->redirect_url ?? null;

            if (!$response) {
                throw new Exception('Failed to create Midtrans transaction.');
            }

            MidtransTransaction::create([
                'order_id' => $order->id,
                'midtrans_order_id' => $orderIdMidtrans,
                'raw_response' => json_encode($response),
            ]);

            Log::info('Midtrans transaction created.', [
                'order_id' => $order->id,
                'mode' => $this->getConfigData('mode'),
                'response' => json_encode($response),
            ]);

            return $response;
        } catch (Throwable $e) {
            Log::error('Midtrans Payment Redirect Failure', [
                'message' => $e->getMessage(),
                'order_id' => $order->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            abort(502, 'Midtrans API communication failed.');
        }
    }

    /**
     * Map order items to Midtrans item_details.
     */
    protected function mapOrderItems($order): array
    {
        $items = [];
        $subtotal = 0;

        foreach ($order->items as $item) {
            $quantity = max(1, (int) $item->quantity);
            $price = max(0, (float) $item->price);

            $items[] = [
                'id' => (string) $item->id,
                'price' => $price,
                'quantity' => $quantity,
                'name' => substr($item->name ?? 'Item', 0, 50),
            ];

            $subtotal += $price * $quantity;
        }

        // Add shipping cost if present
        if ($order->shipping_amount > 0) {
            $items[] = [
                'id' => 'shipping',
                'price' => (float) $order->shipping_amount,
                'quantity' => 1,
                'name' => 'Shipping Fee',
            ];

            $subtotal += (float) $order->shipping_amount;
        }

        // Add adjustment if subtotal != grand_total (Midtrans requires matching totals)
        $difference = round($order->grand_total - $subtotal, 2);
        if (abs($difference) > 0.01) {
            $items[] = [
                'id' => 'adjustment',
                'price' => $difference,
                'quantity' => 1,
                'name' => 'Adjustment',
            ];
        }

        return $items;
    }


    /**
     * Retrieve Midtrans server key.
     */
    public function getServerKey(): ?string
    {
        $key = $this->getConfigData('server_key');
        Log::debug('Midtrans Server Key Selected', [
            'key_set' => !empty($key),
            'mode' => $this->getConfigData('mode') ?? 'sandbox',
        ]);
        return $key;
    }

    /**
     * Get payment method image.
     */
    public function getImage(): string
    {
        $url = $this->getConfigData('image');
        return $url ? Storage::url($url) : bagisto_asset('images/cash-on-delivery.png', 'shop');
    }
}
