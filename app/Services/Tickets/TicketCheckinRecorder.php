<?php

namespace App\Services\Tickets;

use App\Models\Booking;
use App\Models\TicketCheckinEvent;
use App\Models\User;
use App\Services\ActivityLogSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TicketCheckinRecorder
{
    public function __construct(
        private readonly Request $request,
        private readonly ActivityLogSanitizer $sanitizer,
    ) {}

    public function record(Booking $booking, User $actor, string $result, ?string $reason = null, array $context = []): TicketCheckinEvent
    {
        return TicketCheckinEvent::query()->create([
            'booking_id' => $booking->getKey(),
            'showtime_id' => $booking->showtime_id,
            'actor_user_id' => $actor->getKey(),
            'actor_role_snapshot' => Str::limit((string) $actor->role?->slug, 64, ''),
            'result' => $result,
            'reason_code' => $reason === null ? null : Str::limit($reason, 64, ''),
            'scanned_at' => now(),
            'request_id' => $this->requestId(),
            'route_name' => Str::limit((string) $this->request->route()?->getName(), 191, ''),
            'safe_ip_hash' => $this->safeIpHash($this->request->ip()),
            'user_agent_summary' => $this->userAgentSummary($this->request->userAgent()),
            'context' => $this->sanitizer->sanitize($context),
        ]);
    }

    private function requestId(): string
    {
        $existing = $this->request->attributes->get('activity_request_id');
        if (is_string($existing)) {
            return $existing;
        }

        $header = trim((string) $this->request->header('X-Request-ID', ''));
        $id = preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $header) === 1 ? $header : (string) Str::uuid();
        $this->request->attributes->set('activity_request_id', $id);

        return $id;
    }

    private function safeIpHash(?string $ip): ?string
    {
        $key = (string) config('app.key');
        if ($ip === null || $ip === '' || $key === '') {
            return null;
        }
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = $decoded === false ? $key : $decoded;
        }

        return hash_hmac('sha256', $ip, hash_hmac('sha256', 'moviemate/activity-ip/v1', $key, true));
    }

    private function userAgentSummary(?string $userAgent): ?string
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return null;
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Trình duyệt khác',
        };
        $platform = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Nền tảng khác',
        };

        return $browser.' / '.$platform;
    }
}
