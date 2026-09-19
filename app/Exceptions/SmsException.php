<?php

namespace App\Exceptions;

class SmsException extends ApiException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 503, 'sms_send_failed');
    }
}
