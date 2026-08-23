<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Services\ActivityLogger;
use App\Services\Tickets\TicketDeliveryOutbox;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class AdminTicketEmailService
{
    public function __construct(
        private readonly TicketDeliveryOutbox $deliveries,
        private readonly ActivityLogger $activities,
    ) {}

    /**
     * Admin yêu cầu gửi hoặc gửi lại vé cho khách hàng.
     */
    public function send(
        Booking $booking,
        ?int $actorUserId
    ): BookingTicketDelivery {
        $booking->loadMissing([
            'user',
            'ticketDelivery',
        ]);

        $recipient = $this->resolveRecipient($booking);

        if ($recipient === null) {
            $this->recordFailure(
                $booking,
                $actorUserId,
                'ticket_recipient_email_missing',
            );

            throw new RuntimeException(
                'Khách hàng chưa có email hợp lệ để nhận vé.'
            );
        }

        try {
            /*
             * Với khách đã đăng nhập, email hiện tại trong tài khoản
             * được ưu tiên làm địa chỉ nhận vé.
             */
            $this->synchronizeAccountEmail(
                $booking,
                $recipient
            );

            $booking->refresh();

            $delivery = $this->deliveries->requestResend(
                $booking,
                $actorUserId,
            );

            $this->recordQueued(
                $booking,
                $delivery,
                $actorUserId,
                $recipient,
            );

            return $delivery;
        } catch (Throwable $exception) {
            $this->recordFailure(
                $booking,
                $actorUserId,
                $this->failureCode($exception),
            );

            throw $exception;
        }
    }

    /**
     * Xác định email sẽ nhận vé.
     *
     * Thứ tự:
     * 1. Email hiện tại của tài khoản.
     * 2. Email snapshot lưu trên booking.
     */
    public function resolveRecipient(
        Booking $booking
    ): ?string {
        $booking->loadMissing('user');

        $accountEmail = $this->normalizeEmail(
            $booking->user?->email
        );

        if ($accountEmail !== null) {
            return $accountEmail;
        }

        return $this->normalizeEmail(
            $booking->customer_email
        );
    }

    /**
     * Cho giao diện Admin biết email đến từ đâu.
     */
    public function recipientSource(
        Booking $booking
    ): string {
        $booking->loadMissing('user');

        if (
            $this->normalizeEmail(
                $booking->user?->email
            ) !== null
        ) {
            return 'account';
        }

        if (
            $this->normalizeEmail(
                $booking->customer_email
            ) !== null
        ) {
            return 'booking';
        }

        return 'missing';
    }

    /**
     * Kiểm tra Admin có email hợp lệ để gửi hay không.
     */
    public function hasRecipient(
        Booking $booking
    ): bool {
        return $this->resolveRecipient($booking) !== null;
    }

    /**
     * Đồng bộ email tài khoản vào snapshot booking.
     */
    private function synchronizeAccountEmail(
        Booking $booking,
        string $recipient
    ): void {
        if ($booking->user === null) {
            return;
        }

        $accountEmail = $this->normalizeEmail(
            $booking->user->email
        );

        if ($accountEmail === null) {
            return;
        }

        if ($booking->customer_email === $accountEmail) {
            return;
        }

        $booking->forceFill([
            'customer_email' => $recipient,
        ])->save();

        Log::notice(
            'Ticket recipient email synchronized from customer account.',
            [
                'booking_id' => $booking->getKey(),
                'user_id' => $booking->user_id,
            ]
        );
    }

    private function recordQueued(
        Booking $booking,
        BookingTicketDelivery $delivery,
        ?int $actorUserId,
        string $recipient
    ): void {
        $source = $this->recipientSource($booking);

        Log::notice(
            'Admin queued ticket email delivery.',
            [
                'booking_id' => $booking->getKey(),
                'ticket_delivery_id' => $delivery->getKey(),
                'actor_user_id' => $actorUserId,
                'recipient_source' => $source,
                'recipient_email_masked' => $this->maskEmail(
                    $recipient
                ),
            ]
        );

        $this->activities->log(
            'booking.ticket_resend_requested',
            $booking,
            [
                'ticket_delivery_id' => $delivery->getKey(),
                'recipient_source' => $source,
                'recipient_email_masked' => $this->maskEmail(
                    $recipient
                ),
            ],
        );
    }

    private function recordFailure(
        Booking $booking,
        ?int $actorUserId,
        string $failureCode
    ): void {
        Log::error(
            'Admin ticket email delivery request failed.',
            [
                'booking_id' => $booking->getKey(),
                'actor_user_id' => $actorUserId,
                'failure_code' => $failureCode,
            ]
        );

        try {
            $this->activities->log(
                'booking.ticket_email_failed',
                $booking,
                [
                    'failure_code' => $failureCode,
                ],
            );
        } catch (Throwable $loggingException) {
            /*
             * Không để lỗi ghi activity che mất lỗi gửi mail gốc.
             */
            Log::error(
                'Unable to persist ticket email failure activity.',
                [
                    'booking_id' => $booking->getKey(),
                    'failure_code' => $failureCode,
                    'logging_exception' => class_basename(
                        $loggingException
                    ),
                ]
            );
        }
    }

    private function normalizeEmail(
        mixed $email
    ): ?string {
        if (! is_string($email)) {
            return null;
        }

        $email = trim($email);

        if ($email === '') {
            return null;
        }

        if (
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            return null;
        }

        return mb_strtolower($email);
    }

    private function failureCode(
        Throwable $exception
    ): string {
        $message = strtolower(
            trim($exception->getMessage())
        );

        if (
            str_contains(
                $message,
                'ticket_booking_not_eligible'
            )
        ) {
            return 'ticket_booking_not_eligible';
        }

        if (
            str_contains(
                $message,
                'email'
            )
        ) {
            return 'ticket_email_error';
        }

        return 'ticket_delivery_queue_failed';
    }

    private function maskEmail(
        string $email
    ): string {
        [$local, $domain] = array_pad(
            explode('@', $email, 2),
            2,
            ''
        );

        if ($domain === '') {
            return '***';
        }

        $visibleCharacters = mb_substr(
            $local,
            0,
            min(2, mb_strlen($local))
        );

        $hiddenCharacters = str_repeat(
            '*',
            max(3, mb_strlen($local) - 2)
        );

        return $visibleCharacters
            .$hiddenCharacters
            .'@'
            .$domain;
    }
}