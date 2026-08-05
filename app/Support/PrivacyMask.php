<?php

namespace App\Support;

final class PrivacyMask
{
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

    private static function firstCharacter(string $value): string
    {
        return $value === '' ? '*' : mb_substr($value, 0, 1);
    }
}
