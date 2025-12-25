<?php

namespace Akara\MidtransPayment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Midtrans\Config;
use Midtrans\CoreApi;
use Midtrans\Notification as MidtransNotification;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Checkout\Helpers\Cart as CartHelper;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Sales\Transformers\OrderResource;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Akara\MidtransPayment\Models\MidtransNotification as MidtransNotificationModel;
use Webkul\Sales\Repositories\InvoiceRepository;

class MidtransCoreController extends Controller
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected CartRepository $cartRepository,
        protected InvoiceRepository $invoiceRepository
    ) {
    }

    protected function configureMidtrans()
    {
        Config::$serverKey = core()->getConfigData('sales.payment_methods.midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = (core()->getConfigData('sales.payment_methods.midtrans.mode') ?? env('MIDTRANS_MODE', 'sandbox')) === 'production';
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Charge endpoint.
     * Request body: { cart_id, payment_type (qris|bank_transfer|gopay), bank (optional) }
     */
    public function charge(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|integer',
            'payment_type' => 'required|string',
        ]);

        $cartId = $request->input('cart_id');
        $paymentType = $request->input('payment_type');
        $bank = $request->input('bank') ?? core()->getConfigData('sales.payment_methods.midtrans.default_bank') ?? env('MIDTRANS_DEFAULT_BANK');

        $cart = $this->cartRepository->find($cartId);
        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        // Create order from cart (same as Xendit approach)
        $data = (new OrderResource($cart))->jsonSerialize();
        $order = $this->orderRepository->create($data);
        $this->orderRepository->update([
            'status' => Order::STATUS_PENDING_PAYMENT,
        ], $order->id);
        dd($order);

        // Clear cart
        try {
            $this->cartRepository->delete($cart->id);
            \Webkul\Checkout\Facades\Cart::deActivateCart();
        } catch (\Throwable $e) {
            Log::warning('Failed to clear cart after order creation', ['cart_id' => $cart->id, 'error' => $e->getMessage()]);
        }

        // Configure Midtrans
        $this->configureMidtrans();

        $amount = round($order->grand_total, 2);
        if ($amount <= 0) {
            return response()->json(['message' => 'Invalid order total'], 400);
        }

        $midtransOrderId = 'order-' . $order->id . '-' . time();

        // Build params
        $params = [
            'payment_type' => $this->mapPaymentTypeToMidtrans($paymentType),
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $order->customer_first_name ?? 'Customer',
                'last_name' => $order->customer_last_name ?? '',
                'email' => $order->customer_email ?? null,
                'phone' => $order->customer_phone ?? null,
            ],
            'item_details' => $this->mapOrderItems($order),
        ];

        // Add payment-specific structure
        if ($paymentType === 'bank_transfer') {
            $params['bank_transfer'] = ['bank' => $bank];
        } elseif ($paymentType === 'gopay') {
            $params['gopay'] = [
                'enable_callback' => true,
                'callback_url' => route('shop.checkout.onepage.success')
            ];
        } // qris has no extra params

        try {
            $response = CoreApi::charge($params);
        } catch (\Throwable $e) {
            Log::error('Midtrans Core API Charge Error', [
                'message' => $e->getMessage(),
                'order_id' => $order->id,
                'midtrans_params' => $params,
            ]);
            return response()->json(['message' => 'Midtrans API error', 'error' => $e->getMessage()], 502);
        }

        // Persist response into order.payment.additional or order.channel_response
        try {
            $payment = $order->payment;
            $additional = (array) json_decode($payment->additional ?? 'null', true) ?? [];
            $additional['midtrans'] = [
                'midtrans_order_id' => $midtransOrderId,
                'response' => $response,
                'created_at' => Carbon::now()->toDateTimeString(),
            ];
            $payment->additional = json_encode($additional);
            $payment->save();

            // also optionally store as channel_response (searchable)
            $order->channel_response = json_encode([
                'midtrans' => [
                    'midtrans_order_id' => $midtransOrderId,
                    'response' => $response,
                ]
            ]);
            $order->save();
        } catch (\Throwable $e) {
            Log::warning('Failed to persist midtrans response', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        // Normalize return payload for frontend
        $payload = $this->normalizeMidtransResponse($response, $midtransOrderId, $order->id);

        return response()->json($payload);
    }

    protected function mapPaymentTypeToMidtrans(string $type): string
    {
        return match ($type) {
            'bank_transfer' => 'bank_transfer',
            'qris' => 'qris',
            'gopay' => 'gopay',
            default => 'bank_transfer'
        };
    }

    protected function mapOrderItems($order): array
    {
        $items = [];
        $subtotal = 0;
        foreach ($order->items as $item) {
            $qty = max(1, (int) $item->quantity);
            $price = max(0.0, (float) $item->price);
            $items[] = [
                'id' => (string) $item->id,
                'price' => $price,
                'quantity' => $qty,
                'name' => substr($item->name ?? 'Item', 0, 50),
            ];
            $subtotal += $price * $qty;
        }

        if ($order->shipping_amount > 0) {
            $items[] = [
                'id' => 'shipping',
                'price' => (float) $order->shipping_amount,
                'quantity' => 1,
                'name' => 'Shipping Fee'
            ];
            $subtotal += (float) $order->shipping_amount;
        }

        $difference = round($order->grand_total - $subtotal, 2);
        if (abs($difference) > 0.01) {
            $items[] = [
                'id' => 'adjustment',
                'price' => $difference,
                'quantity' => 1,
                'name' => 'Service Fee'
            ];
        }

        return $items;
    }

    protected function normalizeMidtransResponse($response, $midtransOrderId, $orderId)
    {
        $payload = [
            'order_id' => $orderId,
            'midtrans_order_id' => $midtransOrderId,
            'status' => $response['transaction_status'] ?? ($response['status_code'] ?? 'pending'),
            'raw' => $response,
        ];

        // VA (bank_transfer) — midtrans returns va_numbers or permata_va_number
        if (!empty($response['va_numbers'])) {
            $payload['payment_type'] = 'bank_transfer';
            $payload['va_number'] = $response['va_numbers'][0]['va_number'];
            $payload['bank'] = $response['va_numbers'][0]['bank'];
            $payload['instruction'] = $response['status_message'] ?? null;
            $payload['expire_time'] = $response['expiry_time'] ?? null;
        } elseif (!empty($response['permata_va_number'])) {
            $payload['payment_type'] = 'bank_transfer';
            $payload['va_number'] = $response['permata_va_number'];
            $payload['bank'] = 'permata';
            $payload['instruction'] = $response['status_message'] ?? null;
            $payload['expire_time'] = $response['expiry_time'] ?? null;
        } elseif (!empty($response['qr_string']) || !empty($response['qr_code'])) {
            $payload['payment_type'] = 'qris';
            // Midtrans Core v2 may return actions/qr_acct/qr_string — provide QR image data or link if available
            $payload['qr_string'] = $response['qr_string'] ?? ($response['actions'][0]['url'] ?? null);
            $payload['qr_url'] = $response['qr_code'] ?? null;
            $payload['expire_time'] = $response['expiry_time'] ?? null;
        } elseif (!empty($response['actions'])) {
            // e.g., gopay actions
            $payload['payment_type'] = $response['payment_type'] ?? 'unknown';
            $payload['actions'] = $response['actions'];
            $payload['expire_time'] = $response['expiry_time'] ?? null;
        } else {
            $payload['payment_type'] = $response['payment_type'] ?? 'unknown';
        }

        return $payload;
    }

    /**
     * Webhook handler from Midtrans Notification
     */
    public function notification(Request $request)
    {
        $this->configureMidtrans();

        try {
            $notif = new MidtransNotification();

            $midtransOrderId = $notif->order_id ?? null;
            $transactionId = $notif->transaction_id ?? null;
            $transactionStatus = $notif->transaction_status ?? null;
            $fraudStatus = $notif->fraud_status ?? null;

            $expectedSignature = hash(
                'sha512',
                $midtransOrderId .
                $notif->status_code .
                $notif->gross_amount .
                Config::$serverKey
            );

            if (!hash_equals($expectedSignature, $notif->signature_key)) {
                abort(403, 'Invalid Midtrans signature');
            }


            // find order by midtrans_order_id -> search in orders' channel_response or payment.additional
            $order = $this->findOrderByMidtransOrderId($midtransOrderId);
            if (!$order) {
                Log::warning('Midtrans notification: order not found', [
                    'midtrans_order_id' => $midtransOrderId,
                    'payload' => $request->all()
                ]);

                return response()->json([
                    'status' => 'failed',
                    'code' => 404,
                    'error' => 'order_not_found',
                    'midtrans_order_id' => $midtransOrderId,
                    'processed_at' => now()->toISOString(),
                ], 404);
            }

            /**
             * UPSERT midtrans_transactions table
             */
            $transactionData = [
                'order_id' => $order->id,
                'midtrans_order_id' => $midtransOrderId,
                'payment_type' => $notif->payment_type ?? null,
                'transaction_status' => $notif->transaction_status ?? null,
                'fraud_status' => $notif->fraud_status ?? null,
                'transaction_time' => $notif->transaction_time ?? null,
                'status_message' => $notif->status_message ?? null,
                'status_code' => $notif->status_code ?? null,
                'signature_key' => $notif->signature_key ?? null,
                'pop_id' => $notif->pop_id ?? null,
                'merchant_id' => $notif->merchant_id ?? null,
                'gross_amount' => $notif->gross_amount ?? null,
                'customer_name' => $notif->customer_details['full_name'] ?? null,
                'customer_email' => $notif->customer_details['email'] ?? null,
                'currency' => $notif->currency ?? null,
                'raw_response' => $request->all(),
                'expire_time' => $notif->expiry_time ?? null,
            ];

            /**
             * Bank Transfer VA
             */
            if (!empty($notif->va_numbers) && isset($notif->va_numbers[0])) {
                $transactionData['bank'] = $notif->va_numbers[0]->bank ?? null;
                $transactionData['va_number'] = $notif->va_numbers[0]->va_number ?? null;
            }

            /**
             * Permata VA
             */
            if (!empty($notif->permata_va_number)) {
                $transactionData['bank'] = 'permata';
                $transactionData['va_number'] = $notif->permata_va_number;
            }

            /**
             * QRIS (qr_string or redirect URL)
             */
            if (!empty($notif->qr_string)) {
                $transactionData['qr_string'] = $notif->qr_string;
            }
            if (!empty($notif->actions) && isset($notif->actions[0]->url)) {
                $transactionData['qr_url'] = $notif->actions[0]->url;
            }

            /**
             * Expiry
             */
            if (!empty($notif->expiry_time)) {
                $transactionData['expire_time'] = $notif->expiry_time;
            }

            /**
             * FINAL UPSERT midtrans_transactions table
             * This updates ALL columns deterministically.
             */
            $transaction = \Akara\MidtransPayment\Models\MidtransTransaction::updateOrCreate(
                ['midtrans_order_id' => $midtransOrderId],
                $transactionData
            );

            // Reload order with relations (important)
            $order = $this->orderRepository
                ->with(['items', 'invoices'])
                ->find($order->id);
            $invoice = $order->invoices->first();

            /**
             * PAID STATES (capture+accept OR settlement)
             */
            if (
                ($transactionStatus === 'capture' && $fraudStatus === 'accept')
                || $transactionStatus === 'settlement'
            ) {
                // Always move order to processing
                $this->orderRepository->update(
                    ['status' => Order::STATUS_PROCESSING],
                    $order->id
                );

                /**
                 * CREATE invoice only once
                 */
                if (!$invoice) {
                    $this->invoiceRepository->create(
                        data: $this->prepareInvoiceData($order),
                        invoiceState: Invoice::STATUS_PAID,
                        orderState: Order::STATUS_PROCESSING
                    );
                } else {
                    $this->invoiceRepository->updateState(
                        $invoice,
                        Invoice::STATUS_PAID
                    );
                }
            }

            /**
             * PENDING
             */ elseif ($transactionStatus === 'pending') {
                $this->orderRepository->update(
                    ['status' => Order::STATUS_PENDING_PAYMENT],
                    $order->id
                );

                if (!$invoice) {
                    $this->invoiceRepository->create(
                        data: $this->prepareInvoiceData($order),
                        invoiceState: Invoice::STATUS_PENDING_PAYMENT,
                        orderState: Order::STATUS_PENDING_PAYMENT
                    );
                } else {
                    $this->invoiceRepository->updateState(
                        $invoice,
                        Invoice::STATUS_PENDING_PAYMENT
                    );
                }
            }

            /**
             * FAILED / EXPIRED
             */ elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
                $this->orderRepository->cancel($order);
                $this->orderRepository->update(
                    ['status' => Order::STATUS_CANCELED],
                    $order->id
                );

                // Delete any existing invoices
                if ($invoice) {
                    $invoice->delete();
                    Log::info('Invoice deleted for canceled order', [
                        'invoice_id' => $invoice->id,
                        'order_id' => $order->id,
                        'reason' => $transactionStatus,
                    ]);
                }
            }

            /**
             * FRAUD OVERRIDE
             */ elseif ($fraudStatus && $fraudStatus !== 'accept') {
                $this->orderRepository->cancel($order);
                $this->orderRepository->update(
                    ['status' => Order::STATUS_FRAUD],
                    $order->id
                );
                // Delete any existing invoices
                if ($invoice) {
                    $invoice->delete();
                    Log::info('Invoice deleted for fraud order', [
                        'invoice_id' => $invoice->id,
                        'order_id' => $order->id,
                        'reason' => $transactionStatus,
                    ]);
                }
            }

            /**
             * Store in order.payment.additional
             */
            try {
                $payment = $order->payment;
                $additional = json_decode($payment->additional ?? '{}', true);

                if (!isset($additional['midtrans']['notifications'])) {
                    $additional['midtrans']['notifications'] = [];
                }

                $additional['midtrans']['notifications'][] = $request->all();

                $payment->additional = json_encode($additional);
                $payment->save();
            } catch (\Throwable $e) {
                Log::warning('Midtrans notification persist failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            /**
             * Persist into midtrans_notifications table
             */
            MidtransNotificationModel::create([
                'midtrans_transaction_id' => $transaction->id,
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'midtrans_order_id' => $midtransOrderId,
                'transaction_status' => $transactionStatus,
                'processed_at' => now()->toISOString(),
                'message' => 'Notification processed',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Midtrans notification handler error', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 500,
                'error' => 'internal_error',
                'message' => "Terjadi kesalahan pada server",
                'processed_at' => now()->toISOString(),
            ], 500);
        }
    }

    protected function findOrderByMidtransOrderId(?string $midtransOrderId)
    {
        if (!$midtransOrderId) {
            return null;
        }

        $trx = \Akara\MidtransPayment\Models\MidtransTransaction::where('midtrans_order_id', $midtransOrderId)->first();

        if (!$trx) {
            return null;
        }

        return $this->orderRepository->find($trx->order_id);
    }

    /**
     * Order status endpoint for frontend polling
     */
    public function orderStatus($orderId)
    {
        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $payment = $order->payment;
        $additional = (array) json_decode($payment->additional ?? 'null', true) ?? [];

        $midtrans = $additional['midtrans'] ?? null;
        $lastResponse = $midtrans['response'] ?? null;

        $status = $order->status;

        return response()->json([
            'order_id' => $order->id,
            'status' => $status,
            'midtrans' => $midtrans,
            'last_response' => $lastResponse,
        ]);
    }


    protected function prepareInvoiceData($order)
    {
        $invoiceData = [
            'order_id' => $order->id,
            'invoice' => [
                'items' => []
            ]
        ];

        foreach ($order->items as $item) {
            $qty = $item->qty_ordered - $item->qty_invoiced;

            if ($qty > 0) {
                $invoiceData['invoice']['items'][$item->id] = $qty;
            }
        }

        return $invoiceData;
    }

}
