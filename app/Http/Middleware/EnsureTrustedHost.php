<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(rtrim($request->getHost(), '.'));
        $trustedHosts = config('trustedproxy.hosts', []);
        $appHost = parse_url((string) config('app.url', ''), PHP_URL_HOST);
        $paymentHosts = config('payment.public_hosts', []);

        if (is_array($trustedHosts) && is_string($appHost) && $appHost !== '') {
            $trustedHosts[] = strtolower(rtrim($appHost, '.'));
        }
        if (is_array($trustedHosts) && is_array($paymentHosts)) {
            foreach ($paymentHosts as $paymentHost) {
                if (is_string($paymentHost) && trim($paymentHost) !== '') {
                    $trustedHosts[] = strtolower(rtrim(trim($paymentHost), '.'));
                }
            }
        }

        abort_unless(
            is_array($trustedHosts) && in_array($host, $trustedHosts, true),
            Response::HTTP_BAD_REQUEST,
            'Invalid Host header.',
        );

        return $next($request);
    }
}
