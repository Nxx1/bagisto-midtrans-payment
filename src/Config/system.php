<?php

return [
    [
        'key' => 'sales.payment_methods.midtrans',
        'name' => 'Midtrans',
        'info' => 'Midtrans Payment Gateway',
        'sort' => 4,
        'fields' => [
            [
                'name' => 'title',
                'title' => 'admin::app.configuration.index.sales.payment-methods.title',
                'type' => 'text',
                'depends' => 'active:1',
                'channel_based' => false,
                'locale_based' => false,
                'validation' => 'required_if:active,1',
                'default_value' => 'Midtrans',
            ],
            [
                'name' => 'description',
                'title' => 'Description',
                'type' => 'textarea',
                'channel_based' => false,
                'locale_based' => false,
                'default_value' => 'Pay securely using Midtrans payment gateway.',
            ],
            [
                'name' => 'server_key',
                'title' => 'Server Key',
                'type' => 'password',
                'channel_based' => false,
                'locale_based' => false,
                'info' => 'Server key from your Midtrans dashboard (Settings → Access Keys).',
            ],
            [
                'name' => 'client_key',
                'title' => 'Client Key',
                'type' => 'text',
                'channel_based' => false,
                'locale_based' => false,
                'info' => 'Client key used for frontend Snap.js integration.',
            ],
            [
                'name' => 'callback_signature_key',
                'title' => 'Callback Signature Key',
                'type' => 'password',
                'channel_based' => false,
                'locale_based' => false,
                'info' => 'Optional signature key for validating webhook notifications.',
            ],
            [
                'name' => 'generate_invoice',
                'title' => 'admin::app.configuration.index.sales.payment-methods.generate-invoice',
                'type' => 'boolean',
                'default_value' => false,
                'channel_based' => false,
                'locale_based' => false,
            ],
            [
                'name' => 'invoice_status',
                'depends' => 'generate_invoice:1',
                'validation' => 'required_if:generate_invoice,1',
                'title' => 'admin::app.configuration.index.sales.payment-methods.set-invoice-status',
                'type' => 'select',
                'options' => [
                    [
                        'title' => 'admin::app.configuration.index.sales.payment-methods.pending',
                        'value' => 'pending',
                    ],
                    [
                        'title' => 'admin::app.configuration.index.sales.payment-methods.paid',
                        'value' => 'paid',
                    ],
                ],
                'info' => 'admin::app.configuration.index.sales.payment-methods.generate-invoice-applicable',
                'channel_based' => false,
                'locale_based' => false,
            ],
            [
                'name' => 'order_status',
                'title' => 'admin::app.configuration.index.sales.payment-methods.set-order-status',
                'type' => 'select',
                'options' => [
                    [
                        'title' => 'admin::app.configuration.index.sales.payment-methods.pending',
                        'value' => 'pending',
                    ],
                    [
                        'title' => 'admin::app.configuration.index.sales.payment-methods.pending-payment',
                        'value' => 'pending_payment',
                    ],
                    [
                        'title' => 'admin::app.configuration.index.sales.payment-methods.processing',
                        'value' => 'processing',
                    ],
                ],
                'info' => 'admin::app.configuration.index.sales.payment-methods.generate-invoice-applicable',
                'channel_based' => false,
                'locale_based' => false,
            ],
            [
                'name' => 'mailing_address',
                'title' => 'admin::app.configuration.index.sales.payment-methods.mailing-address',
                'type' => 'textarea',
                'channel_based' => false,
                'locale_based' => false,
            ],
            [
                'name' => 'mode',
                'title' => 'Mode',
                'type' => 'select',
                'options' => [
                    ['title' => 'Sandbox', 'value' => 'sandbox'],
                    ['title' => 'Production', 'value' => 'production'],
                ],
                'default_value' => 'sandbox',
            ],
            [
                'name' => 'active',
                'title' => 'Enable Midtrans',
                'type' => 'boolean',
                'default_value' => true,
            ],
        ],
    ],
];
