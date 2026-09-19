<?php

return [

    'sms' => [
        // log | array | eskiz
        'driver' => env('SMS_DRIVER', 'log'),

        'eskiz' => [
            'base_url' => env('ESKIZ_BASE_URL', 'https://notify.eskiz.uz'),
            'email' => env('ESKIZ_EMAIL'),
            'password' => env('ESKIZ_PASSWORD'),
            // Eskiz'da ro'yxatdan o'tgan sender name
            'from' => env('ESKIZ_FROM', '4546'),
        ],
    ],

];
