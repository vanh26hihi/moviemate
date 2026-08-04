<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DiagnoseHttpsConfiguration extends Command
{
    protected $signature = 'app:https-diagnostics';

    protected $description = 'Inspect public URL, proxy, host, and session settings without printing secrets';

    public function handle(): int
    {
        $appUrl = (string) config('app.url', '');
        $scheme = strtolower((string) parse_url($appUrl, PHP_URL_SCHEME));
        $host = strtolower(rtrim((string) parse_url($appUrl, PHP_URL_HOST), '.'));
        $loginUrl = route('login');
        $returnUrl = route('payments.vnpay.return');
        $ipnUrl = route('payments.vnpay.ipn');
        $paymentHosts = $this->normalizedHosts(config('payment.public_hosts'));
        $hostAccepted = $host !== '' && in_array($host, $paymentHosts, true);
        $hotUrl = is_file(public_path('hot'))
            ? trim((string) file_get_contents(public_path('hot')))
            : '';

        $this->components->twoColumnDetail('Environment', (string) app()->environment());
        $this->components->twoColumnDetail('APP_URL', $appUrl !== '' ? $appUrl : 'unset');
        $this->components->twoColumnDetail('APP_URL scheme', $scheme !== '' ? $scheme : 'unset');
        $this->components->twoColumnDetail('APP_URL host', $host !== '' ? $host : 'unset');
        $this->components->twoColumnDetail('APP_URL uses HTTPS', $this->yesNo($scheme === 'https'));
        $this->components->twoColumnDetail('Trusted proxy mode', $this->proxyMode());
        $this->components->twoColumnDetail('Secure session cookie', $this->yesNo(config('session.secure') === true));
        $this->components->twoColumnDetail('Session SameSite', (string) config('session.same_site', 'unset'));
        $this->components->twoColumnDetail('Session domain', filled(config('session.domain')) ? 'configured' : 'host-only');
        $this->components->twoColumnDetail('Login URL', $loginUrl);
        $this->components->twoColumnDetail('VNPAY Return URL', $returnUrl);
        $this->components->twoColumnDetail('VNPAY IPN URL', $ipnUrl);
        $this->components->twoColumnDetail('APP_URL host allowed for payments', $this->yesNo($hostAccepted));
        $this->components->twoColumnDetail('Configuration cached', $this->yesNo(app()->configurationIsCached()));
        $this->components->twoColumnDetail('Vite development server active', $this->yesNo($hotUrl !== ''));

        $warnings = [];
        if ($scheme !== 'https') {
            $warnings[] = 'APP_URL is HTTP; set it to the public HTTPS URL before tunnel or payment testing.';
        }
        if ($scheme === 'https' && config('session.secure') !== true) {
            $warnings[] = 'SESSION_SECURE_COOKIE should be true for an HTTPS tunnel.';
        }
        if (! $hostAccepted) {
            $warnings[] = 'APP_URL host is not included in PAYMENT_PUBLIC_HOSTS.';
        }
        if (strtolower((string) parse_url($loginUrl, PHP_URL_SCHEME)) !== $scheme || $scheme === '') {
            $warnings[] = 'The generated login route does not match the configured APP_URL scheme.';
        }
        if ($this->proxyMode() === 'none') {
            $warnings[] = 'No trusted proxies are configured, so forwarded HTTPS will not be recognized.';
        }
        if ($scheme === 'https' && strtolower((string) parse_url($hotUrl, PHP_URL_SCHEME)) === 'http') {
            $warnings[] = 'Vite hot mode is HTTP and would cause mixed content; stop it and use npm run build for the HTTPS tunnel test.';
        }
        if (config('trustedproxy.wildcard_requested') && ! config('trustedproxy.local_wildcard_enabled')) {
            $warnings[] = 'A wildcard proxy was ignored; it is allowed only with the explicit switch in APP_ENV=local.';
        }

        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }

        if ($warnings === []) {
            $this->components->info('HTTPS configuration contracts are consistent. Verify live forwarded headers in the browser smoke test.');
        }

        return self::SUCCESS;
    }

    private function proxyMode(): string
    {
        $proxies = config('trustedproxy.proxies');

        if ($proxies === '*') {
            return 'local wildcard';
        }
        if (! is_array($proxies) || $proxies === []) {
            return 'none';
        }

        $loopbacks = array_filter($proxies, static fn (mixed $proxy): bool => is_string($proxy)
            && ($proxy === '::1' || str_starts_with($proxy, '127.')));

        return count($loopbacks) === count($proxies)
            ? 'loopback only ('.count($proxies).' entries)'
            : 'explicit IP/CIDR list ('.count($proxies).' entries)';
    }

    /** @return array<int, string> */
    private function normalizedHosts(mixed $hosts): array
    {
        if (! is_array($hosts)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): ?string => is_string($value)
                ? strtolower(rtrim(trim($value), '.'))
                : null,
            $hosts,
        )));
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
