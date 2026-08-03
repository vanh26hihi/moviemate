<?php

namespace App\Services\ZaloPay;

use Illuminate\Http\JsonResponse;

class ZaloPayCallbackResponseFactory
{
    private const SUCCESS = 1;

    private const TRANSIENT_ERROR = 0;

    private const PERMANENT_ERROR = 2;

    public function success(): JsonResponse
    {
        return $this->response(self::SUCCESS, 'Success');
    }

    public function permanent(string $message): JsonResponse
    {
        return $this->response(self::PERMANENT_ERROR, $message);
    }

    public function transient(): JsonResponse
    {
        return $this->response(self::TRANSIENT_ERROR, 'Temporary processing error');
    }

    private function response(int $code, string $message): JsonResponse
    {
        return response()->json([
            'return_code' => $code,
            'return_message' => $message,
        ], 200);
    }
}
