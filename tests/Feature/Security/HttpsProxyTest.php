<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HttpsProxyTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_ORIGIN = 'https://temporary-domain.ngrok-free.dev';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => self::PUBLIC_ORIGIN,
            'payment.public_hosts' => ['temporary-domain.ngrok-free.dev'],
            'session.domain' => null,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'trustedproxy.proxies' => ['127.0.0.1', '::1'],
            'trustedproxy.hosts' => ['localhost', '127.0.0.1', '::1', 'temporary-domain.ngrok-free.dev'],
        ]);
        $this->app->make(Vite::class)->useHotFile(storage_path('framework/testing-vite.hot'));

        Route::middleware('web')->get('/_test/https-probe', static function (Request $request): array {
            return [
                'secure' => $request->isSecure(),
                'scheme' => $request->getScheme(),
                'host' => $request->getHost(),
                'port' => $request->getPort(),
                'login' => route('login'),
                'vnpay_return' => route('payments.vnpay.return'),
                'vnpay_ipn' => route('payments.vnpay.ipn'),
            ];
        });
    }

    public function test_trusted_forwarded_request_is_https_and_generates_public_urls(): void
    {
        $this->forwardedGet('/_test/https-probe')
            ->assertOk()
            ->assertJson([
                'secure' => true,
                'scheme' => 'https',
                'host' => 'temporary-domain.ngrok-free.dev',
                'port' => 443,
                'login' => self::PUBLIC_ORIGIN.'/login',
                'vnpay_return' => self::PUBLIC_ORIGIN.'/payments/vnpay/return',
                'vnpay_ipn' => self::PUBLIC_ORIGIN.'/payments/vnpay/ipn',
            ]);
    }

    public function test_untrusted_request_cannot_spoof_forwarded_scheme_or_host(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'attacker.example',
                'X-Forwarded-Port' => '443',
            ])
            ->get('http://temporary-domain.ngrok-free.dev/_test/https-probe')
            ->assertOk()
            ->assertJson([
                'secure' => false,
                'scheme' => 'http',
                'host' => 'temporary-domain.ngrok-free.dev',
                'port' => 80,
            ]);
    }

    public function test_trusted_proxy_cannot_forward_an_unapproved_host(): void
    {
        $this->forwardedGet('/_test/https-probe', 'attacker.example')
            ->assertBadRequest();
    }

    public function test_explicit_proxy_cidr_is_honored(): void
    {
        config(['trustedproxy.proxies' => ['10.20.0.0/16']]);

        $this->forwardedGet('/_test/https-probe', remoteAddress: '10.20.4.8')
            ->assertOk()
            ->assertJsonPath('secure', true);
    }

    public function test_plain_local_http_remains_http_without_a_redirect_loop(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('http://localhost/_test/https-probe')
            ->assertOk()
            ->assertJson([
                'secure' => false,
                'scheme' => 'http',
                'host' => 'localhost',
                'port' => 80,
            ]);

        $this->get('http://localhost/login')->assertOk()->assertHeaderMissing('Location');
    }

    public function test_login_and_registration_forms_render_with_https_actions_and_assets(): void
    {
        foreach (['/login' => '/login', '/register' => '/register'] as $path => $action) {
            $content = $this->forwardedGet($path)->assertOk()->getContent();

            $this->assertStringContainsString('action="'.self::PUBLIC_ORIGIN.$action.'"', $content);
            $this->assertDoesNotMatchRegularExpression('/(?:action|src|href)=["\']http:\/\//i', $content);
            $this->assertStringNotContainsString('http://localhost', $content);
            $this->assertStringNotContainsString('http://127.0.0.1', $content);
        }
    }

    public function test_https_auth_redirects_session_and_cookie_attributes_remain_secure(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->forwardedPost('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(self::PUBLIC_ORIGIN);
        $this->assertAuthenticatedAs($user);
        $cookieHeader = strtolower(implode('; ', $response->headers->all('Set-Cookie')));
        $this->assertStringContainsString('secure', $cookieHeader);
        $this->assertStringContainsString('httponly', $cookieHeader);
        $this->assertStringContainsString('samesite=lax', $cookieHeader);
        $this->assertStringNotContainsString('domain=localhost', $cookieHeader);

        $this->forwardedGet('/profile')->assertOk();
        $this->forwardedPost('/logout')->assertRedirect(self::PUBLIC_ORIGIN);
        $this->assertGuest();
    }

    public function test_failed_login_redirect_does_not_downgrade(): void
    {
        $this->from(self::PUBLIC_ORIGIN.'/login');

        $this->forwardedPost('/login', [
            'email' => 'missing@example.test',
            'password' => 'incorrect-password',
        ])->assertRedirect(self::PUBLIC_ORIGIN.'/login');
    }

    /** @param array<string, mixed> $data */
    private function forwardedPost(string $path, array $data = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders($this->forwardedHeaders())
            ->post('http://upstream.internal'.$path, $data);
    }

    private function forwardedGet(
        string $path,
        string $forwardedHost = 'temporary-domain.ngrok-free.dev',
        string $remoteAddress = '127.0.0.1',
    ) {
        return $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->withHeaders($this->forwardedHeaders($forwardedHost))
            ->get('http://upstream.internal'.$path);
    }

    /** @return array<string, string> */
    private function forwardedHeaders(string $host = 'temporary-domain.ngrok-free.dev'): array
    {
        return [
            'X-Forwarded-For' => '198.51.100.42',
            'X-Forwarded-Host' => $host,
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Proto' => 'https',
        ];
    }
}
