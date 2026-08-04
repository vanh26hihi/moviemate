<?php

namespace App\Domain\Payments;

final class ZaloPaySigner
{
    public function createCanonical(array $fields): string
    {
        return implode('|', [
            $fields['app_id'],
            $fields['app_trans_id'],
            $fields['app_user'],
            $fields['amount'],
            $fields['app_time'],
            $fields['embed_data'],
            $fields['item'],
        ]);
    }

    public function createMac(array $fields, string $key1): string
    {
        return hash_hmac('sha256', $this->createCanonical($fields), $key1);
    }

    public function queryCanonical(int|string $appId, string $appTransId, string $key1): string
    {
        return $appId.'|'.$appTransId.'|'.$key1;
    }

    public function queryMac(int|string $appId, string $appTransId, string $key1): string
    {
        return hash_hmac('sha256', $this->queryCanonical($appId, $appTransId, $key1), $key1);
    }

    public function callbackMac(string $rawData, string $key2): string
    {
        return hash_hmac('sha256', $rawData, $key2);
    }

    public function verifyCallback(string $rawData, string $mac, string $key2): bool
    {
        return $this->constantTimeHexEquals($this->callbackMac($rawData, $key2), $mac);
    }

    public function returnCanonical(array $fields): string
    {
        return implode('|', [
            $fields['appid'] ?? '',
            $fields['apptransid'] ?? '',
            $fields['pmcid'] ?? '',
            $fields['bankcode'] ?? '',
            $fields['amount'] ?? '',
            $fields['discountamount'] ?? '',
            $fields['status'] ?? '',
        ]);
    }

    public function returnChecksum(array $fields, string $key2): string
    {
        return hash_hmac('sha256', $this->returnCanonical($fields), $key2);
    }

    public function verifyReturn(array $fields, string $checksum, string $key2): bool
    {
        return $this->constantTimeHexEquals($this->returnChecksum($fields, $key2), $checksum);
    }

    public function constantTimeHexEquals(string $expected, string $provided): bool
    {
        if (! preg_match('/^[a-f0-9]{64}$/Di', $provided)) {
            return false;
        }

        return hash_equals(strtolower($expected), strtolower($provided));
    }
}
