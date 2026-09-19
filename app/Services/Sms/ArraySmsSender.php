<?php

namespace App\Services\Sms;

/**
 * Test muhiti: yuborilgan SMS'lar xotirada saqlanadi.
 */
class ArraySmsSender implements SmsSender
{
    /** @var array<int, array{phone: string, message: string}> */
    private static array $messages = [];

    public function send(string $phone, string $message): void
    {
        self::$messages[] = ['phone' => $phone, 'message' => $message];
    }

    /** @return array<int, array{phone: string, message: string}> */
    public static function all(): array
    {
        return self::$messages;
    }

    /** @return array{phone: string, message: string}|null */
    public static function last(): ?array
    {
        return self::$messages === [] ? null : end(self::$messages);
    }

    public static function flush(): void
    {
        self::$messages = [];
    }
}
