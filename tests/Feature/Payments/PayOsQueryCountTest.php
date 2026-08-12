<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Services\Payments\PaymentInitiationService;
use App\Services\Payments\PaymentReturnTokenService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PayOsQueryCountTest extends PayOsPaymentTestCase
{
    public function test_payos_customer_provider_and_admin_query_counts_are_bounded(): void
    {
        $this->withoutVite();
        $this->seedRbac();

        $scenario = $this->bookingScenario();
        $this->get(route('user.bookings.checkout', [
            'showtime' => $scenario['showtime'],
            'selected_seats' => $scenario['seats'][0]->id,
        ]))->assertOk();
        $this->post(route('user.bookings.food.store'), [
            'customer_email' => 'queries@example.test',
            'skip_food' => 1,
        ])->assertRedirect(route('user.bookings.review'));
        $selection = $this->measure(fn () => $this->get(route('user.bookings.review'))->assertOk());

        $booking = $this->payableBooking();
        $beforeHttp = 0;
        Http::fake(function (Request $request) use (&$beforeHttp) {
            $beforeHttp = count(DB::getQueryLog());
            $data = $request->data();
            $responseData = [
                'orderCode' => $data['orderCode'], 'amount' => $data['amount'], 'currency' => 'VND',
                'paymentLinkId' => '124c33293c43417ab7879e14c8d9eb18', 'status' => 'PENDING',
                'checkoutUrl' => 'https://pay.payos.vn/web/124c33293c43417ab7879e14c8d9eb18',
            ];

            return Http::response($this->providerEnvelope($responseData));
        });
        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = app(PaymentInitiationService::class)->initiate($booking, 'payos');
        DB::disableQueryLog();
        $payment = $result->payment;

        Http::fake(fn () => Http::response($this->providerEnvelope($this->queryData($payment))));
        $state = app(PaymentReturnTokenService::class)->issue($payment);
        $return = $this->measure(fn () => $this->get(route('payments.payos.return', [
            'orderCode' => $payment->order_code,
            'state' => $state,
        ]))->assertRedirect());

        $body = $this->webhookBody($payment);
        $webhook = $this->measure(fn () => $this->postJson(route('payments.payos.webhook'), $body)->assertOk());
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);

        $manager = $this->userWithRole('manager');
        $adminIndex = $this->measure(
            fn () => $this->actingAs($manager)->get(route('admin.payments.index'))->assertOk(),
        );
        $adminDetail = $this->measure(
            fn () => $this->actingAs($manager)->get(route('admin.payments.show', $payment))->assertOk(),
        );

        $counts = compact('selection', 'beforeHttp', 'return', 'webhook', 'adminIndex', 'adminDetail');
        $diagnostic = json_encode($counts, JSON_THROW_ON_ERROR);
        $this->assertLessThanOrEqual(19, $selection, 'Payment selection page query count is unbounded. '.$diagnostic);
        $this->assertLessThanOrEqual(14, $beforeHttp, 'payOS initiation performs too many queries before HTTP. '.$diagnostic);
        $this->assertLessThanOrEqual(15, $return, 'payOS return query count is unbounded. '.$diagnostic);
        $this->assertLessThanOrEqual(22, $webhook, 'payOS webhook finalization query count is unbounded. '.$diagnostic);
        $this->assertLessThanOrEqual(12, $adminIndex, 'Admin payment index query count is unbounded. '.$diagnostic);
        $this->assertLessThanOrEqual(15, $adminDetail, 'Admin payment detail query count is unbounded. '.$diagnostic);

        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'PAYOS_QUERY_COUNTS='.json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    /** @param callable():mixed $callback */
    private function measure(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $callback();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
