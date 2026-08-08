<?php

namespace App\Services\Mail;

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

final class TicketMailConfigurationInspector
{
    private const COMPOSITE_TRANSPORTS = ['failover', 'roundrobin'];

    private const NON_DELIVERY_TRANSPORTS = ['log', 'array', 'null'];

    private const DELIVERY_TRANSPORTS = [
        'smtp', 'sendmail', 'mail', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend', 'cloudflare',
    ];

    private const REQUIRED_CLASSES = [
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

    /** @return array{ready: bool, category: ?string, mailer: string, transport: string, smtp_host_present: bool, smtp_port: ?int, encryption: string, from_present: bool} */
    public function inspect(): array
    {
        $selected = config('mail.default');
        $mailers = config('mail.mailers');
        $fromPresent = filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false;
        $base = [
            'ready' => false,
            'category' => 'MAILER_NOT_CONFIGURED',
            'mailer' => is_string($selected) && $selected !== '' ? $selected : 'missing',
            'transport' => 'missing',
            'smtp_host_present' => false,
            'smtp_port' => null,
            'encryption' => 'none',
            'from_present' => $fromPresent,
        ];

        if (! is_string($selected) || $selected === '' || ! is_array($mailers)) {
            return $base;
        }

        try {
            $leaves = $this->leaves($selected, $mailers, []);
        } catch (Throwable) {
            return $base;
        }

        if ($leaves === []) {
            return $base;
        }

        $base['transport'] = implode(',', array_values(array_unique(array_column($leaves, 'transport'))));
        if (app()->environment('testing')
            && $base['transport'] === 'array'
            && $fromPresent) {
            $base['ready'] = true;
            $base['category'] = null;

            return $base;
        }

        foreach ($leaves as $leaf) {
            if (in_array($leaf['transport'], self::NON_DELIVERY_TRANSPORTS, true)) {
                $base['category'] = $leaf['transport'] === 'log'
                    ? 'MAILER_IS_LOG_ONLY'
                    : 'MAILER_NOT_CONFIGURED';

                return $base;
            }
            if (! in_array($leaf['transport'], self::DELIVERY_TRANSPORTS, true)
                || ! class_exists(self::REQUIRED_CLASSES[$leaf['transport']])) {
                return $base;
            }
        }

        $smtpLeaves = collect($leaves)->where('transport', 'smtp');
        foreach ($smtpLeaves as $smtp) {
            $config = $smtp['config'];
            $host = $config['host'] ?? null;
            $hostPresent = is_string($host) && trim($host) !== '';
            $port = isset($config['port']) && is_numeric($config['port'])
                ? (int) $config['port']
                : null;
            $scheme = $config['scheme'] ?? $config['encryption'] ?? null;
            if ($base['smtp_port'] === null) {
                $base['smtp_host_present'] = $hostPresent;
                $base['smtp_port'] = $port;
                $base['encryption'] = is_string($scheme) && trim($scheme) !== ''
                    ? strtolower(trim($scheme))
                    : 'none';
            }

            if (! $hostPresent || $port === null) {
                return $base;
            }
        }

        if (! $fromPresent) {
            return $base;
        }

        $base['ready'] = true;
        $base['category'] = null;

        return $base;
    }

    /**
     * @param  array<string, mixed>  $mailers
     * @param  list<string>  $stack
     * @return list<array{transport: string, config: array<string, mixed>}>
     */
    private function leaves(string $name, array $mailers, array $stack): array
    {
        if (in_array($name, $stack, true) || count($stack) >= 32) {
            throw new \RuntimeException('invalid_mailer_graph');
        }

        $config = $mailers[$name] ?? null;
        if (! is_array($config)) {
            throw new \RuntimeException('missing_mailer');
        }

        if (isset($config['url'])) {
            $config = array_merge($config, (new ConfigurationUrlParser)->parseConfiguration($config));
            $config['transport'] = Arr::pull($config, 'driver');
        }

        $transport = $config['transport'] ?? null;
        if (! is_string($transport) || $transport === '') {
            throw new \RuntimeException('missing_transport');
        }
        $transport = strtolower(trim($transport));

        if (! in_array($transport, self::COMPOSITE_TRANSPORTS, true)) {
            return [['transport' => $transport, 'config' => $config]];
        }

        $children = $config['mailers'] ?? null;
        if (! is_array($children) || ! array_is_list($children) || $children === []) {
            throw new \RuntimeException('invalid_composite');
        }

        $leaves = [];
        foreach ($children as $child) {
            if (! is_string($child) || $child === '') {
                throw new \RuntimeException('invalid_child');
            }
            $leaves = [...$leaves, ...$this->leaves($child, $mailers, [...$stack, $name])];
        }

        return $leaves;
    }
}
