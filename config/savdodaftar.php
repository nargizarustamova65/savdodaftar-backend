<?php

return [

    // Qo'llab-quvvatlanadigan tillar (Accept-Language header orqali tanlanadi)
    'locales' => ['uz', 'ru'],

    'otp' => [
        'length' => 6,
        // Kod amal qilish muddati (soniya). TZ: 60-120
        'ttl' => (int) env('OTP_TTL', 120),
        // Noto'g'ri urinishlar soni. TZ: 3-5
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        // Qayta yuborish oralig'i (soniya). TZ: 1 daqiqada 1 marta
        'resend_after' => (int) env('OTP_RESEND_AFTER', 60),
        // Bitta raqamga kunlik SMS limiti
        'daily_limit' => (int) env('OTP_DAILY_LIMIT', 10),
        // Bitta IP dan kunlik SMS limiti
        'ip_daily_limit' => (int) env('OTP_IP_DAILY_LIMIT', 50),
        // Eskiz'da tasdiqlangan shablon bilan mos bo'lishi kerak
        'sms_template' => env('OTP_SMS_TEMPLATE', 'Savdodaftar ilovasiga kirish kodi: {code}'),
        // Local/dev muhitda doimiy kod (production'da ishlamaydi)
        'debug_code' => env('OTP_DEBUG_CODE'),
    ],

    'pin' => [
        'max_attempts' => (int) env('PIN_MAX_ATTEMPTS', 5),
        // Limit tugagach bloklash muddati (soniya)
        'lockout_seconds' => (int) env('PIN_LOCKOUT_SECONDS', 300),
        // SMS orqali tasdiqlangandan keyin PIN'ni yangilash uchun berilgan vaqt (soniya)
        'reset_window' => (int) env('PIN_RESET_WINDOW', 600),
    ],

    'inventory' => [
        // Savdo/chiqimda qoldiq manfiyga tushishiga ruxsat (bozorchi omborni to'liq yuritmasa)
        'allow_negative_stock' => (bool) env('INVENTORY_ALLOW_NEGATIVE_STOCK', false),
        // Mahsulot rasmlari saqlanadigan disk (`php artisan storage:link` kerak)
        'image_disk' => env('PRODUCT_IMAGE_DISK', 'public'),
        // Rasm hajmi limiti (KB)
        'image_max_kb' => (int) env('PRODUCT_IMAGE_MAX_KB', 4096),
    ],

    'billing' => [
        // TZ 31: Pro narxi (so'm) va obuna muddati (kun)
        'pro_price' => (int) env('PRO_PRICE', 49000),
        'pro_days' => (int) env('PRO_DAYS', 30),

        'payme' => [
            'merchant_id' => env('PAYME_MERCHANT_ID'),
            // Webhook Basic auth paroli (Payme kassa kaliti)
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
        // Zaxira fayllari saqlanadigan disk (production'da s3 tavsiya etiladi)
        'disk' => env('BACKUP_DISK', 'local'),
        // Har bir foydalanuvchi uchun saqlanadigan oxirgi zaxiralar soni
        'keep' => (int) env('BACKUP_KEEP', 10),
    ],

];
