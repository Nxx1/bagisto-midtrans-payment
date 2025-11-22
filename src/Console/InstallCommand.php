<?php

namespace Akara\MidtransPayment\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'akara:install-midtrans';
    protected $description = 'Publish Midtrans config & migrations, migrate and clear cache';

    public function handle()
    {
        $this->info('Publishing Midtrans config and migrations...');
        $this->call('vendor:publish', ['--tag' => 'midtrans-payment', '--force' => true]);

        $this->info('Running migrations...');
        $this->call('migrate', ['--force' => true]);

        $this->info('Clearing and optimizing caches...');
        $this->call('optimize:clear');

        $this->info('Midtrans module installed. Configure keys in Admin -> Configuration -> Sales -> Payment Methods -> Midtrans.');
        return 0;
    }
}
