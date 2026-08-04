<?php

namespace Tests\Unit\Payments;

use App\Domain\Payments\VnpaySigner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VnpaySignerTest extends TestCase
{
    public function test_payment_canonicalization_is_sorted_rfc_1738_and_excludes_hash_fields(): void
    {
        $signer = new VnpaySigner;
        $parameters = [
            'vnp_TxnRef' => 'MM123',
            'state' => 'merchant-state',
            'vnp_OrderInfo' => 'MovieMate booking A 1',
            'vnp_Amount' => 5000000,
            'vnp_SecureHash' => str_repeat('f', 128),
            'vnp_BankCode' => '',
        ];

        $this->assertSame(
            'vnp_Amount=5000000&vnp_OrderInfo=MovieMate+booking+A+1&vnp_TxnRef=MM123',
            $signer->paymentCanonical($parameters),
        );
        $this->assertTrue($signer->verifyPayment(
            $parameters,
            $signer->signPayment($parameters, str_repeat('s', 32)),
            str_repeat('s', 32),
        ));
        $this->assertTrue($signer->verifyPayment(
            $parameters,
            strtoupper($signer->signPayment($parameters, str_repeat('s', 32))),
            str_repeat('s', 32),
        ));
    }

    public function test_tampering_duplicates_arrays_and_malformed_hashes_are_rejected(): void
    {
        $signer = new VnpaySigner;
        $secret = str_repeat('x', 32);
        $parameters = ['vnp_Amount' => '5000000', 'vnp_TxnRef' => 'MM123'];
        $signature = $signer->signPayment($parameters, $secret);
        $this->assertSame(hash_hmac('sha512', $signer->paymentCanonical($parameters), $secret), $signature);

        $this->assertFalse($signer->verifyPayment($parameters + ['vnp_ResponseCode' => '00'], $signature, $secret));
        $this->assertFalse($signer->constantTimeHexEquals($signature, 'not-a-hash'));

        foreach (['vnp_TxnRef=A&vnp_TxnRef=B', 'vnp_TxnRef[]=A'] as $query) {
            try {
                $signer->parseQueryString($query);
                $this->fail('Malformed query should be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $signer->paymentCanonical(['vnp_Amount' => ['5000000']]);
    }

    public function test_query_dr_uses_the_documented_exact_field_order(): void
    {
        $fields = [
            'vnp_OrderInfo' => 'Query order', 'vnp_IpAddr' => '127.0.0.1',
            'vnp_CreateDate' => '20260805120000', 'vnp_TransactionDate' => '20260805110000',
            'vnp_TxnRef' => 'MM123', 'vnp_TmnCode' => 'MOVIE123',
            'vnp_Command' => 'querydr', 'vnp_Version' => '2.1.0', 'vnp_RequestId' => 'REQ1',
        ];

        $this->assertSame(
            'REQ1|2.1.0|querydr|MOVIE123|MM123|20260805110000|20260805120000|127.0.0.1|Query order',
            (new VnpaySigner)->queryRequestCanonical($fields),
        );
    }
}
