<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * @param  string  $phone  E.164 formatda (+998XXXXXXXXX)
     *
     * @throws \App\Exceptions\SmsException
     */
    public function send(string $phone, string $message): void;
}
