<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Dev muhit: SMS matni faqat log'ga yoziladi.
 */
class LogSmsService implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS (log driver)', ['phone' => $phone, 'message' => $message]);
    }
}
