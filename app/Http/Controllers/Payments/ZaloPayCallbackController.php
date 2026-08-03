<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\PaymentConfigurationException;
use App\Http\Controllers\Controller;
use App\Services\ZaloPay\ZaloPayCallbackResponseFactory;
use App\Services\ZaloPay\ZaloPayCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZaloPayCallbackController extends Controller
{
    public function __invoke(Request $request, ZaloPayCallbackResponseFactory $responses): JsonResponse
    {
        try {
            return app(ZaloPayCallbackService::class)->handle($request);
        } catch (PaymentConfigurationException $exception) {
            Log::critical('ZaloPay callback configuration is invalid.', [
                'exception' => $exception::class,
            ]);

            return $responses->permanent('Merchant configuration rejected');
        }
    }
}
