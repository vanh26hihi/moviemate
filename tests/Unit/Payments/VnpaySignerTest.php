<?php

namespace Tests\Unit\Payments;

use App\Domain\Payments\VnpaySigner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VnpaySignerTest extends TestCase
{
    public function test_official_php_210_canonicalization_parity_uses_an_independent_fixed_vector(): void
    {
        $parameters = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => 'TEST1234',
            'vnp_Amount' => '5000000',
            'vnp_CreateDate' => '20260805120000',
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '203.0.113.7',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'MovieMate booking MMT123',
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => 'https://merchant.example.com/payments/vnpay/return',
            'vnp_ExpireDate' => '20260805121500',
            'vnp_TxnRef' => 'MM260805120000ABC123',
        ];
        $expectedCanonical = 'vnp_Amount=5000000&vnp_Command=pay&vnp_CreateDate=20260805120000&vnp_CurrCode=VND&vnp_ExpireDate=20260805121500&vnp_IpAddr=203.0.113.7&vnp_Locale=vn&vnp_OrderInfo=MovieMate+booking+MMT123&vnp_OrderType=other&vnp_ReturnUrl=https%3A%2F%2Fmerchant.example.com%2Fpayments%2Fvnpay%2Freturn&vnp_TmnCode=TEST1234&vnp_TxnRef=MM260805120000ABC123&vnp_Version=2.1.0';
        $expectedHmac = 'c69085b05a57e8fc15cec26d0b943c2c46f8718502752db7f8763fd54c2a81f1cca645be2939de7ac3fce195eebf9bd66a25b4f42cac4c93008ad98b5ce688be';
        $secret = 'synthetic-vnpay-secret-1234567890';
        $signer = new VnpaySigner;

        $this->assertSame($expectedCanonical, $signer->paymentCanonical($parameters));
        $this->assertStringContainsString('vnp_ReturnUrl=https%3A%2F%2Fmerchant.example.com', $expectedCanonical);
        $this->assertStringNotContainsString('https%253A', $expectedCanonical);
        $this->assertSame($expectedHmac, $signer->signPayment($parameters, $secret));
        $this->assertSame(355, strlen($expectedCanonical));
        $this->assertSame(
            'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?'.$expectedCanonical.'&vnp_SecureHash='.$expectedHmac,
            'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?'
                .$signer->paymentCanonical($parameters)
                .'&vnp_SecureHash='.$signer->signPayment($parameters, $secret),
        );

        foreach (['vnp_Amount', 'vnp_ReturnUrl', 'vnp_OrderInfo'] as $field) {
            $tampered = $parameters;
            $tampered[$field] .= 'X';
            $this->assertFalse($signer->verifyPayment($tampered, $expectedHmac, $secret));
        }
    }

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
        $this->assertSame(
            'vnp_OrderInfo=A+B%2FC%3A%7E',
            $signer->paymentCanonical(['vnp_OrderInfo' => 'A B/C:~']),
        );
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
