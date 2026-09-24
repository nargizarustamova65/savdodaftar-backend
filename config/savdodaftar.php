<?php

return [
    'locales' => ['uz', 'ru'],
    'otp' => [
        'length' => 6,
        'ttl' => (int) env('OTP_TTL', 120),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'resend_after' => (int) env('OTP_RESEND_AFTER', 60),
        'daily_limit' => (int) env('OTP_DAILY_LIMIT', 10),
        'ip_daily_limit' => (int) env('OTP_IP_DAILY_LIMIT', 50),
        'sms_template' => env('OTP_SMS_TEMPLATE', 'Savdodaftar ilovasiga kirish kodi: {code}'),
        'debug_code' => env('OTP_DEBUG_CODE'),
    ],
    'pin' => [
        'max_attempts' => (int) env('PIN_MAX_ATTEMPTS', 5),
        'lockout_seconds' => (int) env('PIN_LOCKOUT_SECONDS', 300),
        'reset_window' => (int) env('PIN_RESET_WINDOW', 600),
    ],
    'inventory' => [
        'allow_negative_stock' => (bool) env('INVENTORY_ALLOW_NEGATIVE_STOCK', false),
        'image_disk' => env('PRODUCT_IMAGE_DISK', 'public'),
        'image_max_kb' => (int) env('PRODUCT_IMAGE_MAX_KB', 4096),
    ],
    'billing' => [
        // Paid plans are deliberately configured server-side; the app only displays this response.
        'standard_price' => (int) env('STANDARD_PRICE', 12000),
        'pro_price' => (int) env('PRO_PRICE', 49000),
        'standard_days' => (int) env('STANDARD_DAYS', 30),
        'pro_days' => (int) env('PRO_DAYS', 30),
        'payme' => [
            'merchant_id' => env('PAYME_MERCHANT_ID'),
            'key' => env('PAYME_KEY'),
            'checkout_url' => env('PAYME_CHECKOUT_URL', 'https://checkout.paycom.uz'),
        ],
        'click' => [
            'merchant_id' => env('CLICK_MERCHANT_ID'),
            'service_id' => env('CLICK_SERVICE_ID'),
            'secret_key' => env('CLICK_SECRET_KEY'),
            'checkout_url' => env('CLICK_CHECKOUT_URL', 'https://my.click.uz/services/pay'),
        ],
    ],
    'backup' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'keep' => (int) env('BACKUP_KEEP', 10),
    ],
];
