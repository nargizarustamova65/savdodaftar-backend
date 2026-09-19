<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Biznes xatoliklari uchun yagona API javob formati.
 * `code` — mobil ilova uchun mashina o'qiy oladigan identifikator.
 */
class ApiException extends Exception
{
    public function __construct(
        string $message,
        protected int $status = 422,
        protected ?string $errorCode = null,
        protected array $meta = [],
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ];

        if ($this->meta !== []) {
            $payload['meta'] = $this->meta;
        }

        return response()->json($payload, $this->status);
    }
}
