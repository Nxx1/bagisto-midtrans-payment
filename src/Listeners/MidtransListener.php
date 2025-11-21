<?php

namespace Akara\MidtransPayment\Listeners;

use Log;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;

/**
 * Generate Invoice Event handler
 */
class MidtransListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected InvoiceRepository $invoiceRepository
    ) {
    }

    /**
     * Generate a new invoice.
     *
     * @param  object  $order
     * @return void
     */
    public function handle($order)
    {
        Log::info("Order Listener Data", $order->jsonSerialize());

        if ($order->payment->method == 'midtrans') {
            Cart::removeCart($order->cart);

            $this->invoiceRepository->create(
                data: $this->prepareInvoiceData($order),
                invoiceState: core()->getConfigData('sales.payment_methods.midtrans.invoice_status'),
                orderState: core()->getConfigData('sales.payment_methods.midtrans.order_status')
            );
        }
    }

    /**
     * Prepares order's invoice data for creation.
     *
     * @return array
     */
    protected function prepareInvoiceData($order)
    {
        $invoiceData = ['order_id' => $order->id];

        foreach ($order->items as $item) {
            $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $invoiceData;
    }
}
