<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\PaymentConfigurationException;
use App\Http\Controllers\Controller;
use App\Services\Vnpay\VnpayIpnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class VnpayIpnController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            return response()->json(app(VnpayIpnService::class)->handle($request));
        } catch (PaymentConfigurationException $exception) {
            Log::critical('VNPAY IPN configuration is invalid.', ['exception' => $exception::class]);
        } catch (Throwable $exception) {
            Log::error('VNPAY IPN processing failed.', ['exception' => $exception::class]);
        }

        return response()->json(['RspCode' => '99', 'Message' => 'Unknown error']);
    }
}
