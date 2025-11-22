# Bagisto Midtrans Payment Gateway

A complete Midtrans Snap payment gateway integration for **Bagisto 2.3.x**, providing secure checkout redirection, Webhook notifications, transaction persistence, and Bagisto-native payment method registration.

## Requirements

- PHP **8.1+**
- Bagisto **2.3.x**
- Midtrans Server Key & Client Key

# Installation

### 1. Require Package

```bash
composer require akara/bagisto-midtrans-payment
```

### 2. Run Installer

```bash
php artisan akara:install-midtrans
```

This command:

- Publishes config
- Runs migrations
- Registers payment method
- Clears caches

### 3. Environment Variables

```
MIDTRANS_MODE=sandbox
MIDTRANS_SERVER_KEY=your-server-key
MIDTRANS_CLIENT_KEY=your-client-key
```

# Midtrans Dashboard Settings

| Setting                      | URL                                                |
| ---------------------------- | -------------------------------------------------- |
| Payment Notification URL     | `https://yourdomain.com/api/midtrans/notification` |
| Recurring Notification URL   | `https://yourdomain.com/api/midtrans/notification` |
| Pay Account Notification URL | `https://yourdomain.com/api/midtrans/notification` |

# License
MIT License

Copyright (c) 2025 N.Pratama

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
