<?php

namespace Akara\MidtransPayment\Providers;

use Akara\MidtransPayment\Payment\MidtransPayment;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Akara\MidtransPayment\Payment\MidtransPayment as MidtransPaymentClass;

class MidtransServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge all configuration files

        $this->app->bind('midtrans', function () {
            return new MidtransPayment(
                app(\Webkul\Sales\Repositories\OrderRepository::class),
                app(\Webkul\Sales\Repositories\InvoiceRepository::class),
                app(\Webkul\Checkout\Repositories\CartRepository::class),
            );
        });

        $this->mergeConfigFrom(__DIR__ . '/../Config/payment-methods.php', 'payment_methods');
        $this->mergeConfigFrom(__DIR__ . '/../Config/system.php', 'core');
    }

    public function boot()
    {
        // Register event provider
        $this->app->register(MidtransEventServiceProvider::class);

        // Load routes, views, migrations
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'midtrans');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Publish files for artisan command "vendor:publish --tag=midtrans-payment"
        $this->publishes([
            __DIR__ . '/../Config/payment-methods.php' => config_path('payment_methods.php'),
            __DIR__ . '/../Config/midtrans.php' => config_path('midtrans.php'),
            __DIR__ . '/../Config/system.php' => config_path('system.php'),
            __DIR__ . '/../Database/Migrations/' => database_path('migrations'),
        ], 'midtrans-payment');

        // Register CLI install command (if running via console)
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Akara\MidtransPayment\Console\InstallCommand::class,
            ]);
        }

        // Register the payment method into Bagisto
        Event::listen('bagisto.payment.methods', function ($methods) {
            $methods->add(MidtransPaymentClass::class);
        });
    }
}
