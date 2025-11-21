<?php

namespace Akara\MidtransPayment\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MidtransEventServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen('checkout.order.save.after', 'Akara\MidtransPayment\Listeners\MidtransListener@handle');
    }
}
