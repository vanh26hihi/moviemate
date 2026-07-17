<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PayosService
{
    public function createPaymentLink(Booking $booking): array
    {
        $this->ensureConfigured();

        $booking->loadMissing(['user', 'showtime.movie', 'bookingSeats.seat']);

        $amount = (int) round((float) $booking->total_amount);
        $orderCode = (int) $booking->id;
        $description = 'MMT'.$booking->id;
        $returnUrl = route('payment.payos.return', $booking);
        $cancelUrl = route('payment.payos.cancel', $booking);

        $payload = [
            'orderCode' => $orderCode,
            'amount' => $amount,
            'description' => $description,
            'buyerName' => $booking->user?->name,
            'buyerEmail' => $booking->user?->email,
            'buyerPhone' => $booking->user?->phone,
            'items' => [
                [
                    'name' => Str::limit('Ve + do an '.($booking->showtime?->movie?->title ?? 'MovieMate'), 80, ''),
                    'quantity' => 1,
                    'price' => $amount,
                ],
            ],
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl,
        ];

        $payload['signature'] = $this->signature([
            'amount' => $payload['amount'],
            'cancelUrl' => $payload['cancelUrl'],
            'description' => $payload['description'],
            'orderCode' => $payload['orderCode'],
            'returnUrl' => $payload['returnUrl'],
        ]);

        $response = Http::withHeaders($this->headers())
            ->acceptJson()
            ->post($this->baseUrl().'/v2/payment-requests', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Khong tao duoc link thanh toan payOS: '.$response->body());
        }

        $json = $response->json();

        if (($json['code'] ?? null) !== '00' || empty($json['data']['checkoutUrl'])) {
            throw new RuntimeException('payOS tra ve du lieu khong hop le: '.json_encode($json, JSON_UNESCAPED_UNICODE));
        }

        return $json['data'];
    }

    public function getPaymentInfo(int|string $orderCode): array
    {
        $this->ensureConfigured();

        $response = Http::withHeaders($this->headers())
            ->acceptJson()
            ->get($this->baseUrl().'/v2/payment-requests/'.$orderCode);

        if (! $response->successful()) {
            throw new RuntimeException('Khong lay duoc trang thai payOS: '.$response->body());
        }

        $json = $response->json();

        if (($json['code'] ?? null) !== '00') {
            throw new RuntimeException('payOS tra ve trang thai khong hop le: '.json_encode($json, JSON_UNESCAPED_UNICODE));
        }

        return $json['data'] ?? [];
    }

    public function verifyWebhook(array $payload): array
    {
        $this->ensureConfigured();

        $data = $payload['data'] ?? null;
        $signature = $payload['signature'] ?? null;

        if (! is_array($data) || ! is_string($signature)) {
            throw new RuntimeException('Webhook payOS thieu data hoac signature.');
        }

        if (! hash_equals($this->signature($data), $signature)) {
            throw new RuntimeException('Chu ky webhook payOS khong hop le.');
        }

        return $data;
    }

    public function isPaidStatus(?string $status): bool
    {
        return in_array(strtoupper((string) $status), ['PAID', 'SUCCESS', 'SUCCEEDED'], true);
    }

    protected function headers(): array
    {
        return [
            'x-client-id' => config('services.payos.client_id'),
            'x-api-key' => config('services.payos.api_key'),
        ];
    }

    protected function signature(array $data): string
    {
        ksort($data);

        $signatureData = collect($data)
            ->map(fn ($value, $key) => $key.'='.$this->normalizeSignatureValue($value))
            ->implode('&');

        return hash_hmac('sha256', $signatureData, (string) config('services.payos.checksum_key'));
    }

    protected function normalizeSignatureValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    protected function ensureConfigured(): void
    {
        if (! config('services.payos.client_id') || ! config('services.payos.api_key') || ! config('services.payos.checksum_key')) {
            throw new RuntimeException('Chua cau hinh PAYOS_CLIENT_ID, PAYOS_API_KEY hoac PAYOS_CHECKSUM_KEY trong .env.');
        }
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.payos.base_url'), '/');
    }
}
