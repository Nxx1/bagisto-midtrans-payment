<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('midtrans_transactions', function (Blueprint $table) {
            $table->timestamp('transaction_time')->nullable()->after('fraud_status');
            $table->string('status_message')->nullable()->after('transaction_time');
            $table->string('status_code')->nullable()->after('status_message');
            $table->text('signature_key')->nullable()->after('status_code');
            $table->string('pop_id')->nullable()->after('signature_key');
            $table->string('merchant_id')->nullable()->after('pop_id');
            $table->decimal('gross_amount', 15, 2)->nullable()->after('merchant_id');
            $table->string('customer_name')->nullable()->after('gross_amount');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('currency', 10)->nullable()->after('customer_email');
        });
    }

    public function down(): void
    {
        Schema::table('midtrans_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'transaction_time',
                'status_message',
                'status_code',
                'signature_key',
                'pop_id',
                'merchant_id',
                'gross_amount',
                'customer_name',
                'customer_email',
                'currency',
            ]);
        });
    }
};
