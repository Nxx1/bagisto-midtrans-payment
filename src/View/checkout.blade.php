@extends('shop::layouts.master')

@section('content')
    <div class="container mt-10 mb-10 text-center">
        <h2 class="text-xl font-semibold mb-6">Complete Your Payment</h2>
        <p>Order #{{ $order->id }} — Total: Rp {{ number_format($order->grand_total, 0, ',', '.') }}</p>
        <button id="pay-button" class="btn btn-primary mt-5 px-6 py-2">Pay Now</button>
    </div>

    <script src="https://app{{ $isProduction ? '' : '.sandbox' }}.midtrans.com/snap/snap.js"
        data-client-key="{{ core()->getConfigData('sales.payment_methods.midtrans.client_key') }}"></script>

    <script type="text/javascript">
        document.getElementById('pay-button').addEventListener('click', function() {
            snap.pay('{{ $snapToken }}', {
                onSuccess: function() {
                    window.location.href = "{{ route('shop.checkout.onepage.success') }}";
                },
                onPending: function() {
                    window.location.href = "{{ route('shop.customers.account.orders.index') }}";
                },
                onError: function() {
                    alert('Payment failed. Please try again.');
                }
            });
        });
    </script>
@endsection
