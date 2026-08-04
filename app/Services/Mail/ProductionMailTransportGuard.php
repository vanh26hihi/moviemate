<?php

namespace App\Services\Mail;

use App\Exceptions\UnsafeProductionMailConfiguration;
use Aws\Ses\SesClient;
use Aws\SesV2\SesV2Client;
use Illuminate\Support\Arr;
use Illuminate\Support\ConfigurationUrlParser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Throwable;

final class ProductionMailTransportGuard
{
    private const MAX_DEPTH = 32;

    private const COMPOSITE_TRANSPORTS = ['failover', 'roundrobin'];

    private const FORBIDDEN_TRANSPORTS = ['log', 'array', 'null', 'failover', 'roundrobin'];

    /**
     * Laravel 13 delivery transports whose implementation can be identified
     * from configuration and whose required package must be installed.
     */
    private const INSPECTABLE_LEAF_TRANSPORTS = [
        'smtp' => EsmtpTransportFactory::class,
        'sendmail' => SendmailTransport::class,
        'mail' => SendmailTransport::class,
        'ses' => SesClient::class,
        'ses-v2' => SesV2Client::class,
        'mailgun' => MailgunTransportFactory::class,
        'postmark' => PostmarkTransportFactory::class,
        'resend' => \Resend::class,
        'cloudflare' => HttpClient::class,
    ];

    public function assertSafeForProduction(?string $mailer = null): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $mailers = config('mail.mailers');
        if (! is_array($mailers)) {
            throw UnsafeProductionMailConfiguration::atPath(
                ['mailers'],
                'The mailer registry is missing or malformed.',
            );
        }

        if (config('mail.driver') !== null) {
            throw UnsafeProductionMailConfiguration::atPath(
                ['legacy-driver'],
                'Legacy mail.driver configuration is ambiguous and is not supported.',
            );
        }

        $selected = $mailer ?? config('mail.default');
        if (! $this->isSafeMailerName($selected)) {
            throw UnsafeProductionMailConfiguration::atPath(
                ['default'],
                'The selected mailer name is missing or malformed.',
            );
        }

        foreach (array_keys($mailers) as $name) {
            if (! $this->isSafeMailerName($name)) {
                throw UnsafeProductionMailConfiguration::atPath(
                    ['mailers'],
                    'The mailer registry contains a malformed name.',
                );
            }
        }

        $allowed = $this->allowedLeafTransports($mailers);
        $this->visitMailer($selected, [$selected], [], $mailers, $allowed);
    }

    /**
     * @param  array<string, mixed>  $mailers
     * @return array<string, true>
     */
    private function allowedLeafTransports(array $mailers): array
    {
        $configured = config('mail.production_allowed_transports');
        if (! is_string($configured) || trim($configured) === '') {
            throw UnsafeProductionMailConfiguration::atPath(
                ['allowed-transports'],
                'The production leaf-transport allow-list is missing or malformed.',
            );
        }

        $allowed = [];
        foreach (explode(',', $configured) as $value) {
            $transport = strtolower(trim($value));
            if (! $this->isSafeTransportIdentifier($transport)
                || isset($allowed[$transport])
                || in_array($transport, self::FORBIDDEN_TRANSPORTS, true)) {
                throw UnsafeProductionMailConfiguration::atPath(
                    ['allowed-transports'],
                    'The production leaf-transport allow-list contains an invalid, duplicate, or forbidden value.',
                );
            }

            $allowed[$transport] = true;
        }

        $known = $this->configuredTransportIdentities($mailers);
        foreach ($allowed as $transport => $_approved) {
            if (! isset($known[$transport])) {
                throw UnsafeProductionMailConfiguration::atPath(
                    ['allowed-transports'],
                    'The production leaf-transport allow-list contains an unknown value.',
                );
            }
        }

        return $allowed;
    }

    /**
     * @param  array<string, mixed>  $mailers
     * @return array<string, true>
     */
    private function configuredTransportIdentities(array $mailers): array
    {
        $known = [];

        foreach ($mailers as $config) {
            if (! is_array($config)) {
                continue;
            }

            try {
                $config = $this->resolveUrlConfiguration($config);
            } catch (Throwable) {
                continue;
            }

            $transport = $this->normalizeTransport($config['transport'] ?? null);
            if ($transport !== null && $this->isInspectableLeafTransport($transport)) {
                $known[$transport] = true;
            }
        }

        return $known;
    }

    /**
     * @param  list<string>  $path
     * @param  list<string>  $stack
     * @param  array<string, mixed>  $mailers
     * @param  array<string, true>  $allowed
     */
    private function visitMailer(
        string $name,
        array $path,
        array $stack,
        array $mailers,
        array $allowed,
    ): void {
        if (! $this->isSafeMailerName($name)) {
            throw UnsafeProductionMailConfiguration::atPath(
                $path,
                'A mailer name is malformed.',
            );
        }

        if (count($stack) >= self::MAX_DEPTH) {
            throw UnsafeProductionMailConfiguration::atPath(
                $path,
                'The mailer graph exceeds the maximum supported depth.',
            );
        }

        if (in_array($name, $stack, true)) {
            throw UnsafeProductionMailConfiguration::atPath(
                $path,
                'A circular mailer reference was detected.',
            );
        }

        if (! array_key_exists($name, $mailers)) {
            throw UnsafeProductionMailConfiguration::atPath(
                $path,
                'A referenced mailer is not defined.',
            );
        }

        $config = $mailers[$name];
        if (! is_array($config)) {
            throw UnsafeProductionMailConfiguration::atPath(
                $path,
                'The mailer configuration is malformed.',
            );
        }

        try {
            $config = $this->resolveUrlConfiguration($config);
        } catch (Throwable) {
            throw UnsafeProductionMailConfiguration::atPath(
                $path,
                'The mailer URL configuration cannot be safely inspected.',
            );
        }

        $transport = $this->normalizeTransport($config['transport'] ?? null);
        if ($transport === null) {
            throw UnsafeProductionMailConfiguration::atPath(
                $path,
                'The mailer transport is missing or malformed.',
            );
        }

        $transportPath = $name === $transport ? $path : [...$path, $transport];

        if (in_array($transport, self::COMPOSITE_TRANSPORTS, true)) {
            $children = $config['mailers'] ?? null;
            if (! is_array($children) || ! array_is_list($children) || $children === []) {
                throw UnsafeProductionMailConfiguration::atPath(
                    $transportPath,
                    'The composite mailer list is empty or malformed.',
                );
            }

            $seenChildren = [];
            foreach ($children as $child) {
                if (! $this->isSafeMailerName($child)) {
                    throw UnsafeProductionMailConfiguration::atPath(
                        $transportPath,
                        'The composite mailer list contains a malformed reference.',
                    );
                }
                if (isset($seenChildren[$child])) {
                    throw UnsafeProductionMailConfiguration::atPath(
                        $transportPath,
                        'The composite mailer list contains an ambiguous duplicate reference.',
                    );
                }
                $seenChildren[$child] = true;

                $this->visitMailer(
                    $child,
                    [...$transportPath, $child],
                    [...$stack, $name],
                    $mailers,
                    $allowed,
                );
            }

            return;
        }

        if (in_array($transport, self::FORBIDDEN_TRANSPORTS, true)) {
            throw UnsafeProductionMailConfiguration::atPath(
                $transportPath,
                'The graph reaches a non-delivery transport.',
            );
        }

        if (! isset($allowed[$transport])) {
            throw UnsafeProductionMailConfiguration::atPath(
                $transportPath,
                'The graph reaches a leaf transport that is not explicitly approved.',
            );
        }
    }

    /**
     * Mirror Laravel MailManager URL precedence without retaining or exposing secrets.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function resolveUrlConfiguration(array $config): array
    {
        if (! isset($config['url'])) {
            return $config;
        }

        $config = array_merge(
            $config,
            (new ConfigurationUrlParser)->parseConfiguration($config),
        );
        $config['transport'] = Arr::pull($config, 'driver');

        return $config;
    }

    private function normalizeTransport(mixed $transport): ?string
    {
        if (! is_string($transport)) {
            return null;
        }

        $transport = strtolower(trim($transport));

        return $this->isSafeTransportIdentifier($transport) ? $transport : null;
    }

    private function isSafeMailerName(mixed $value): bool
    {
        return is_string($value)
            && $value !== '0'
            && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}\z/D', $value) === 1;
    }

    private function isSafeTransportIdentifier(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}\z/D', $value) === 1;
    }

    private function isInspectableLeafTransport(string $transport): bool
    {
        $requiredClass = self::INSPECTABLE_LEAF_TRANSPORTS[$transport] ?? null;

        return $requiredClass !== null && class_exists($requiredClass);
    }
}
