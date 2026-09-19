<?php

namespace App\Services\Sms;

use App\Exceptions\SmsException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Eskiz.uz SMS xizmati.
 * Token 30 kun amal qiladi, 29 kun cache'da saqlanadi; 401 bo'lsa qayta login qilinadi.
 */
class EskizSmsService implements SmsSender
{
    private const TOKEN_CACHE_KEY = 'eskiz:token';

    public function __construct(private readonly array $config) {}

    public function send(string $phone, string $message): void
    {
        $response = $this->request($this->token(), $phone, $message);

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->request($this->token(), $phone, $message);
        }

        if ($response->failed()) {
            Log::error('Eskiz: SMS yuborilmadi', [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new SmsException(__('auth.otp.send_failed'));
        }
    }

    private function request(string $token, string $phone, string $message): Response
    {
        return Http::withToken($token)
            ->acceptJson()
            ->asForm()
            ->timeout(10)
            ->post($this->config['base_url'].'/api/message/sms/send', [
                'mobile_phone' => ltrim($phone, '+'),
                'message' => $message,
                'from' => $this->config['from'],
            ]);
    }

    private function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addDays(29), fn () => $this->login());
    }

    private function login(): string
    {
        $response = Http::acceptJson()
            ->asForm()
            ->timeout(10)
            ->post($this->config['base_url'].'/api/auth/login', [
                'email' => $this->config['email'],
                'password' => $this->config['password'],
            ]);

        $token = $response->json('data.token');

        if ($response->failed() || ! $token) {
            Log::error('Eskiz: login muvaffaqiyatsiz', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new SmsException(__('auth.otp.send_failed'));
        }

        return $token;
    }
}
