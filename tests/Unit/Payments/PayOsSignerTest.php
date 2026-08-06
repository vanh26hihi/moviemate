<?php

namespace Tests\Unit\Payments;

use App\Domain\Payments\PayOsSigner;
use PHPUnit\Framework\TestCase;

class PayOsSignerTest extends TestCase
{
    private PayOsSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer = new PayOsSigner;
    }

    public function test_create_signature_matches_deterministic_official_canonical_contract(): void
    {
        $fields = [
            'amount' => 50000,
            'cancelUrl' => 'https://movie.test/payments/payos/cancel',
            'description' => 'MM123456',
            'orderCode' => 123456,
            'returnUrl' => 'https://movie.test/payments/payos/return',
        ];

        $this->assertSame(
            'amount=50000&cancelUrl=https://movie.test/payments/payos/cancel&description=MM123456&orderCode=123456&returnUrl=https://movie.test/payments/payos/return',
            $this->signer->paymentRequestCanonical($fields),
        );
        $this->assertSame(
            'a2e8cb78afb9b5817d944bbcb8701a7a36d7b23be1938428076cdb094d2e8abc',
            $this->signer->createPaymentRequestSignature($fields, 'payos-test-checksum-key-only'),
        );
    }

    public function test_create_signature_changes_for_every_authoritative_identity_field(): void
    {
        $fields = [
            'amount' => 50000,
            'cancelUrl' => 'https://movie.test/cancel',
            'description' => 'MMABC123',
            'orderCode' => 123456,
            'returnUrl' => 'https://movie.test/return',
        ];
        $signature = $this->signer->createPaymentRequestSignature($fields, 'payos-test-checksum-key-only');

        foreach (['amount' => 50001, 'orderCode' => 123457, 'returnUrl' => 'https://movie.test/other'] as $key => $value) {
            $modified = $fields;
            $modified[$key] = $value;
            $this->assertNotSame(
                $signature,
                $this->signer->createPaymentRequestSignature($modified, 'payos-test-checksum-key-only'),
            );
        }
    }

    public function test_webhook_data_is_sorted_verified_and_constant_time_compatible(): void
    {
        $data = [
            'reference' => 'TF230204212323',
            'orderCode' => 123,
            'amount' => 3000,
            'paymentLinkId' => '124c33293c43417ab7879e14c8d9eb18',
            'currency' => 'VND',
        ];
        $signature = $this->signer->signData($data, 'payos-test-checksum-key-only');

        $this->assertTrue($this->signer->verifyData($data, $signature, 'payos-test-checksum-key-only'));
        $this->assertTrue($this->signer->verifyData($data, strtoupper($signature), 'payos-test-checksum-key-only'));
        foreach (['amount' => 3001, 'orderCode' => 124, 'reference' => 'CHANGED'] as $key => $value) {
            $modified = $data;
            $modified[$key] = $value;
            $this->assertFalse($this->signer->verifyData($modified, $signature, 'payos-test-checksum-key-only'));
        }
        $this->assertFalse($this->signer->verifyData($data, null, 'payos-test-checksum-key-only'));
        $this->assertFalse($this->signer->verifyData($data, 'malformed', 'payos-test-checksum-key-only'));
    }
}
