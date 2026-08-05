<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Services\Mail\TicketMailConfigurationInspector;
use App\Services\Tickets\BookingTicketEligibility;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class TicketMailDiagnostics extends Command
{
    protected $signature = 'tickets:mail-diagnostics {--booking= : Booking ID to inspect without mutation}';

    protected $description = 'Safely diagnose printable-ticket and mail-delivery readiness';

    public function handle(
        TicketMailConfigurationInspector $mailConfiguration,
        BookingTicketEligibility $eligibility,
        Schedule $schedule,
    ): int {
        $bookingId = filter_var($this->option('booking'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if (! $bookingId) {
            $this->error('A positive --booking ID is required.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find($bookingId);
        $delivery = BookingTicketDelivery::query()->where('booking_id', $bookingId)->first();
        $mail = $mailConfiguration->inspect();
        $jobsAvailable = Schema::hasTable('jobs');
        $failedJobsAvailable = Schema::hasTable('failed_jobs');
        $queuedJobs = $jobsAvailable ? DB::table('jobs')->count() : null;
        $failedJobs = $failedJobsAvailable ? DB::table('failed_jobs')->count() : null;
        $event = collect($schedule->events())->first(
            fn ($candidate): bool => str_contains((string) $candidate->command, 'bookings:send-pending-tickets'),
        );
        $scheduled = $event !== null;
        $nextRun = $scheduled ? $event->nextRunDate()->toIso8601String() : 'n/a';
        $verifiedPayment = $booking ? $eligibility->verifiedPayment($booking) : null;
        $recipient = $booking?->recipient_email;
        $recipientValid = is_string($recipient)
            && filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false;
        $queueConnection = (string) config('queue.default', 'missing');
        $queueWorkerRequired = false;
        $rootCauses = $this->rootCauses($mail, $booking, $delivery, $recipientValid);

        $this->table(['Check', 'Safe result'], [
            ['Booking found', $booking ? 'yes' : 'no'],
            ['Booking paid state', $booking?->payment_status === 'paid' && $booking?->booking_status === 'paid' ? 'yes' : 'no'],
            ['Verified payment paid state', $verifiedPayment ? 'yes' : 'no'],
            ['Recipient', $recipientValid ? $this->maskEmail($recipient) : 'missing-or-invalid'],
            ['Default mailer', $mail['mailer']],
            ['Mail transport', $mail['transport']],
            ['Accepted for ticket delivery', $mail['ready'] ? 'yes' : 'no'],
            ['SMTP host present', $mail['smtp_host_present'] ? 'yes' : 'no'],
            ['SMTP port', $mail['smtp_port'] === null ? 'n/a' : (string) $mail['smtp_port']],
            ['Encryption mode', $mail['encryption']],
            ['From address present', $mail['from_present'] ? 'yes' : 'no'],
            ['Queue connection', $queueConnection],
            ['Queue worker required by ticket outbox', $queueWorkerRequired ? 'yes' : 'no (mailer sends synchronously in scheduled command)'],
            ['Jobs table / queued count', $jobsAvailable ? 'yes / '.$queuedJobs : 'no / n/a'],
            ['Failed jobs table / count', $failedJobsAvailable ? 'yes / '.$failedJobs : 'no / n/a'],
            ['Delivery row ID', $delivery?->getKey() ?? 'missing'],
            ['Delivery status', $delivery?->status ?? 'missing'],
            ['Delivery attempts', $delivery?->attempts ?? 0],
            ['Claimed at', $delivery?->processing_started_at?->toIso8601String() ?? 'never'],
            ['Sent at', $delivery?->sent_at?->toIso8601String() ?? 'never'],
            ['Last safe error category', $delivery?->last_error_code ?? 'none'],
            ['Scheduled outbox command', $scheduled ? 'yes' : 'no'],
            ['Next scheduled run', $nextRun],
            ['Ticket route can be generated', $booking && Route::has('user.bookings.ticket') ? 'yes' : 'no'],
            ['Public APP_URL uses HTTPS', str_starts_with((string) config('app.url'), 'https://') ? 'yes' : 'no'],
            ['Root-cause classification', implode(', ', $rootCauses)],
        ]);

        if (! $mail['ready']) {
            $this->error('Configuration currently prevents real ticket-email delivery.');

            return self::FAILURE;
        }

        if (! $booking || ! $recipientValid) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param array{ready: bool, category: ?string} $mail */
    private function rootCauses(
        array $mail,
        ?Booking $booking,
        ?BookingTicketDelivery $delivery,
        bool $recipientValid,
    ): array {
        $causes = [];
        if ($mail['category']) {
            $causes[] = $mail['category'];
        }
        if ($booking && ! $recipientValid) {
            $causes[] = 'RECIPIENT_INVALID';
        }
        if ($booking && $delivery === null) {
            $causes[] = 'OUTBOX_ROW_MISSING';
        }
        if ($delivery?->status === BookingTicketDelivery::STATUS_SENT) {
            $causes[] = 'DELIVERY_ALREADY_SENT';
        }
        if ($mail['category'] === 'MAILER_IS_LOG_ONLY'
            && $delivery?->status === BookingTicketDelivery::STATUS_SENT) {
            $causes[] = 'CODE_DEFECT_CONFIRMED';
        }
        if ($delivery?->status === BookingTicketDelivery::STATUS_PROCESSING
            && $delivery->lease_expires_at?->isPast()) {
            $causes[] = 'DELIVERY_CLAIM_STUCK';
        }
        if ($delivery?->last_error_code === 'smtp_authentication_failed') {
            $causes[] = 'SMTP_AUTHENTICATION_FAILED';
        }
        if ($delivery?->last_error_code === 'smtp_connection_failed') {
            $causes[] = 'SMTP_CONNECTION_FAILED';
        }

        return array_values(array_unique($causes ?: ['INSUFFICIENT_EVIDENCE']));
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
