<?php

namespace Tests\Unit\Payments;

use App\Domain\Payments\ZaloPaySigner;
use PHPUnit\Framework\TestCase;

class ZaloPaySignerTest extends TestCase
{
    private ZaloPaySigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer = new ZaloPaySigner;
    }

    public function test_create_canonical_string_uses_verified_field_order(): void
    {
        $this->assertSame(
            '2553|260804_abcd|user|50000|1785800000000|{"redirecturl":"https://example.test"}|[]',
            $this->signer->createCanonical($this->createFields()),
        );
    }

    public function test_create_mac_is_hmac_sha256_with_key1(): void
    {
        $fields = $this->createFields();
        $this->assertSame(
            hash_hmac('sha256', $this->signer->createCanonical($fields), 'key-one'),
            $this->signer->createMac($fields, 'key-one'),
        );
    }

    public function test_query_canonical_string_includes_key1_as_data(): void
    {
        $this->assertSame('2553|260804_abcd|key-one', $this->signer->queryCanonical(2553, '260804_abcd', 'key-one'));
    }

    public function test_query_mac_uses_key1_as_hmac_key(): void
    {
        $canonical = '2553|260804_abcd|key-one';
        $this->assertSame(
            hash_hmac('sha256', $canonical, 'key-one'),
            $this->signer->queryMac(2553, '260804_abcd', 'key-one'),
        );
    }

    public function test_callback_mac_is_computed_over_untouched_raw_data(): void
    {
        $raw = "{\n\"amount\" : 50000, \"app_id\":2553}";
        $this->assertSame(hash_hmac('sha256', $raw, 'key-two'), $this->signer->callbackMac($raw, 'key-two'));
        $normalized = str_replace([' ', "\n"], '', $raw);
        $this->assertNotSame($this->signer->callbackMac($raw, 'key-two'), $this->signer->callbackMac($normalized, 'key-two'));
    }

    public function test_return_checksum_uses_verified_browser_field_order(): void
    {
        $fields = [
            'appid' => '2553', 'apptransid' => '260804_abcd', 'pmcid' => '38',
            'bankcode' => 'bank', 'amount' => '50000', 'discountamount' => '0', 'status' => '1',
        ];
        $canonical = '2553|260804_abcd|38|bank|50000|0|1';

        $this->assertSame($canonical, $this->signer->returnCanonical($fields));
        $this->assertSame(hash_hmac('sha256', $canonical, 'key-two'), $this->signer->returnChecksum($fields, 'key-two'));
    }

    public function test_verification_is_strict_and_constant_time_safe(): void
    {
        $raw = '{"amount":50000}';
        $mac = $this->signer->callbackMac($raw, 'key-two');

        $this->assertTrue($this->signer->verifyCallback($raw, strtoupper($mac), 'key-two'));
        $this->assertFalse($this->signer->verifyCallback($raw, substr($mac, 1), 'key-two'));
        $this->assertFalse($this->signer->verifyCallback($raw, str_repeat('z', 64), 'key-two'));
    }

    private function createFields(): array
    {
        return [
            'app_id' => 2553,
            'app_trans_id' => '260804_abcd',
            'app_user' => 'user',
            'amount' => 50000,
            'app_time' => 1785800000000,
            'embed_data' => '{"redirecturl":"https://example.test"}',
            'item' => '[]',
        ];
    }
}
