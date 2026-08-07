<?php

namespace App\Support;

final class PrivacyMask
{
    public static function name(?string $name): string
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts) || $parts === []) {
            return 'Khách đặt vé';
        }

        return collect($parts)
            ->map(fn (string $part): string => self::firstCharacter($part).'***')
            ->join(' ');
    }

    public static function email(?string $email): string
    {
        $email = trim((string) $email);
        if ($email === '' || ! str_contains($email, '@')) {
            return 'Chưa có email';
        }

        [$local, $domain] = explode('@', $email, 2);
        $domainParts = explode('.', $domain, 2);
        $domainName = $domainParts[0] ?? '';
        $suffix = isset($domainParts[1]) ? '.'.$domainParts[1] : '';

        return self::firstCharacter($local).'***@'.self::firstCharacter($domainName).'***'.$suffix;
    }

    public static function phone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if (! is_string($phone) || $phone === '') {
            return 'Chưa có số điện thoại';
        }

        return str_repeat('*', max(0, strlen($phone) - 4)).substr($phone, -4);
    }

    private static function firstCharacter(string $value): string
    {
        return $value === '' ? '*' : mb_substr($value, 0, 1);
    }
}
