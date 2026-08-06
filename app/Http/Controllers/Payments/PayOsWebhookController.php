<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\PaymentConfigurationException;
use App\Http\Controllers\Controller;
use App\Services\PayOs\PayOsWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class PayOsWebhookController extends Controller
{
    public function __invoke(Request $request, PayOsWebhookService $webhooks): JsonResponse
    {
        $contentType = strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0]));
        $body = $request->getContent();
        if ($contentType !== 'application/json' || $body === '' || strlen($body) > 32768) {
            return response()->json(['message' => 'Invalid request'], 415);
        }

        try {
            $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || array_is_list($payload)) {
                throw new JsonException('Object required.');
            }
            $webhooks->handle($payload, hash('sha256', $body));
        } catch (JsonException|InvalidArgumentException) {
            return response()->json(['message' => 'Invalid webhook'], 400);
        } catch (PaymentConfigurationException $exception) {
            Log::critical('payOS webhook configuration is invalid.', ['exception' => $exception::class]);

            return response()->json(['message' => 'Merchant configuration rejected'], 503);
        } catch (Throwable $exception) {
            Log::error('payOS webhook processing failed.', ['exception' => $exception::class]);

            return response()->json(['message' => 'Temporary processing error'], 503);
        }

        return response()->json(['message' => 'OK']);
    }
}
