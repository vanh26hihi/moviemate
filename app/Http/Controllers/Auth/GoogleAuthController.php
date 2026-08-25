<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function redirect(): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()->route('login')->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            Log::notice('Google customer authentication was cancelled.', [
                'provider' => 'google',
                'event' => 'customer_cancelled',
            ]);

            return $this->failure('Đăng nhập Google đã bị hủy. Vui lòng thử lại nếu bạn muốn tiếp tục.');
        }

        if (! self::isConfigured()) {
            return $this->failure('Đăng nhập Google chưa được cấu hình.');
        }

        try {
            $identity = $this->verifiedIdentity(Socialite::driver('google')->user());
            [$user, $created] = $this->resolveCustomerWithRaceRetry($identity);
        } catch (GoogleCustomerAuthenticationException $exception) {
            Log::notice('Google customer authentication was rejected.', [
                'provider' => 'google',
                'event' => $exception->event,
            ]);

            return $this->failure($exception->getMessage());
        } catch (Throwable) {
            Log::warning('Google customer authentication failed.', [
                'provider' => 'google',
                'event' => 'provider_or_persistence_failure',
            ]);

            return $this->failure('Không thể đăng nhập bằng Google lúc này. Vui lòng thử lại.');
        }

        if ($created) {
            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * @return array{provider_id: string, email: string, name: string}
     */
    private function verifiedIdentity(SocialiteUser $googleUser): array
    {
        $providerId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawPayload = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];
        $raw = is_array($rawPayload) ? $rawPayload : [];
        $emailVerified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if ($providerId === '' || mb_strlen($providerId) > 255) {
            throw new GoogleCustomerAuthenticationException(
                'Google không cung cấp mã tài khoản hợp lệ.',
                'missing_provider_id'
            );
        }

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản Google phải cung cấp địa chỉ email hợp lệ.',
                'missing_or_invalid_email'
            );
        }

        if ($emailVerified !== true) {
            throw new GoogleCustomerAuthenticationException(
                'Email Google chưa được xác minh nên không thể đăng nhập.',
                'unverified_email'
            );
        }

        $name = trim((string) $googleUser->getName());
        $name = $name !== '' ? $name : Str::before($email, '@');

        return [
            'provider_id' => $providerId,
            'email' => $email,
            'name' => Str::limit($name, 255, ''),
        ];
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomerWithRaceRetry(array $identity): array
    {
        try {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        } catch (UniqueConstraintViolationException) {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        }
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomer(array $identity): array
    {
        $linkedUser = User::query()
            ->with('role')
            ->where('google_id', $identity['provider_id'])
            ->lockForUpdate()
            ->first();

        if ($linkedUser !== null) {
            $this->assertCustomerMayAuthenticate($linkedUser);

            return [$linkedUser, false];
        }

        $emailUser = User::query()
            ->with('role')
            ->where('email', $identity['email'])
            ->lockForUpdate()
            ->first();

        if ($emailUser !== null) {
            $this->assertCustomerMayAuthenticate($emailUser);

            if (filled($emailUser->google_id) && ! hash_equals((string) $emailUser->google_id, $identity['provider_id'])) {
                throw new GoogleCustomerAuthenticationException(
                    'Email này đã được liên kết với một tài khoản Google khác.',
                    'email_linked_to_different_google_account'
                );
            }

            $emailUser->google_id = $identity['provider_id'];
            $emailUser->email_verified_at ??= now();
            $emailUser->save();

            return [$emailUser, false];
        }

        $customerRole = Role::query()
            ->where('slug', 'user')
            ->lockForUpdate()
            ->first();

        if ($customerRole === null) {
            throw new GoogleCustomerAuthenticationException(
                'Không thể tạo tài khoản khách hàng lúc này.',
                'customer_role_missing'
            );
        }

        $user = new User;
        $user->name = $identity['name'];
        $user->email = $identity['email'];
        $user->google_id = $identity['provider_id'];
        $user->email_verified_at = now();
        $user->password = Hash::make(Str::random(64));
        $user->role_id = $customerRole->id;
        $user->status = 'active';
        $user->save();
        $user->setRelation('role', $customerRole);

        return [$user, true];
    }

    private function assertCustomerMayAuthenticate(User $user): void
    {
        if (! $user->isActive()) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản hiện không thể đăng nhập.',
                'inactive_customer'
            );
        }

        if (! $user->hasRole('user')) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản nhân sự không thể đăng nhập qua cổng Google dành cho khách hàng.',
                'privileged_account_collision'
            );
        }
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}

final class GoogleCustomerAuthenticationException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $event)
    {
        parent::__construct($message);
    }
}
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function redirect(): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()->route('login')->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            Log::notice('Google customer authentication was cancelled.', [
                'provider' => 'google',
                'event' => 'customer_cancelled',
            ]);

            return $this->failure('Đăng nhập Google đã bị hủy. Vui lòng thử lại nếu bạn muốn tiếp tục.');
        }

        if (! self::isConfigured()) {
            return $this->failure('Đăng nhập Google chưa được cấu hình.');
        }

        try {
            $identity = $this->verifiedIdentity(Socialite::driver('google')->user());
            [$user, $created] = $this->resolveCustomerWithRaceRetry($identity);
        } catch (GoogleCustomerAuthenticationException $exception) {
            Log::notice('Google customer authentication was rejected.', [
                'provider' => 'google',
                'event' => $exception->event,
            ]);

            return $this->failure($exception->getMessage());
        } catch (Throwable) {
            Log::warning('Google customer authentication failed.', [
                'provider' => 'google',
                'event' => 'provider_or_persistence_failure',
            ]);

            return $this->failure('Không thể đăng nhập bằng Google lúc này. Vui lòng thử lại.');
        }

        if ($created) {
            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * @return array{provider_id: string, email: string, name: string}
     */
    private function verifiedIdentity(SocialiteUser $googleUser): array
    {
        $providerId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawPayload = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];
        $raw = is_array($rawPayload) ? $rawPayload : [];
        $emailVerified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if ($providerId === '' || mb_strlen($providerId) > 255) {
            throw new GoogleCustomerAuthenticationException(
                'Google không cung cấp mã tài khoản hợp lệ.',
                'missing_provider_id'
            );
        }

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản Google phải cung cấp địa chỉ email hợp lệ.',
                'missing_or_invalid_email'
            );
        }

        if ($emailVerified !== true) {
            throw new GoogleCustomerAuthenticationException(
                'Email Google chưa được xác minh nên không thể đăng nhập.',
                'unverified_email'
            );
        }

        $name = trim((string) $googleUser->getName());
        $name = $name !== '' ? $name : Str::before($email, '@');

        return [
            'provider_id' => $providerId,
            'email' => $email,
            'name' => Str::limit($name, 255, ''),
        ];
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomerWithRaceRetry(array $identity): array
    {
        try {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        } catch (UniqueConstraintViolationException) {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        }
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomer(array $identity): array
    {
        $linkedUser = User::query()
            ->with('role')
            ->where('google_id', $identity['provider_id'])
            ->lockForUpdate()
            ->first();

        if ($linkedUser !== null) {
            $this->assertCustomerMayAuthenticate($linkedUser);

            return [$linkedUser, false];
        }

        $emailUser = User::query()
            ->with('role')
            ->where('email', $identity['email'])
            ->lockForUpdate()
            ->first();

        if ($emailUser !== null) {
            $this->assertCustomerMayAuthenticate($emailUser);

            if (filled($emailUser->google_id) && ! hash_equals((string) $emailUser->google_id, $identity['provider_id'])) {
                throw new GoogleCustomerAuthenticationException(
                    'Email này đã được liên kết với một tài khoản Google khác.',
                    'email_linked_to_different_google_account'
                );
            }

            $emailUser->google_id = $identity['provider_id'];
            $emailUser->email_verified_at ??= now();
            $emailUser->save();

            return [$emailUser, false];
        }

        $customerRole = Role::query()
            ->where('slug', 'user')
            ->lockForUpdate()
            ->first();

        if ($customerRole === null) {
            throw new GoogleCustomerAuthenticationException(
                'Không thể tạo tài khoản khách hàng lúc này.',
                'customer_role_missing'
            );
        }

        $user = new User;
        $user->name = $identity['name'];
        $user->email = $identity['email'];
        $user->google_id = $identity['provider_id'];
        $user->email_verified_at = now();
        $user->password = Hash::make(Str::random(64));
        $user->role_id = $customerRole->id;
        $user->status = 'active';
        $user->save();
        $user->setRelation('role', $customerRole);

        return [$user, true];
    }

    private function assertCustomerMayAuthenticate(User $user): void
    {
        if (! $user->isActive()) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản hiện không thể đăng nhập.',
                'inactive_customer'
            );
        }

        if (! $user->hasRole('user')) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản nhân sự không thể đăng nhập qua cổng Google dành cho khách hàng.',
                'privileged_account_collision'
            );
        }
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}

final class GoogleCustomerAuthenticationException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $event)
    {
        parent::__construct($message);
    }
}
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function redirect(): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()->route('login')->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            Log::notice('Google customer authentication was cancelled.', [
                'provider' => 'google',
                'event' => 'customer_cancelled',
            ]);

            return $this->failure('Đăng nhập Google đã bị hủy. Vui lòng thử lại nếu bạn muốn tiếp tục.');
        }

        if (! self::isConfigured()) {
            return $this->failure('Đăng nhập Google chưa được cấu hình.');
        }

        try {
            $identity = $this->verifiedIdentity(Socialite::driver('google')->user());
            [$user, $created] = $this->resolveCustomerWithRaceRetry($identity);
        } catch (GoogleCustomerAuthenticationException $exception) {
            Log::notice('Google customer authentication was rejected.', [
                'provider' => 'google',
                'event' => $exception->event,
            ]);

            return $this->failure($exception->getMessage());
        } catch (Throwable) {
            Log::warning('Google customer authentication failed.', [
                'provider' => 'google',
                'event' => 'provider_or_persistence_failure',
            ]);

            return $this->failure('Không thể đăng nhập bằng Google lúc này. Vui lòng thử lại.');
        }

        if ($created) {
            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * @return array{provider_id: string, email: string, name: string}
     */
    private function verifiedIdentity(SocialiteUser $googleUser): array
    {
        $providerId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawPayload = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];
        $raw = is_array($rawPayload) ? $rawPayload : [];
        $emailVerified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if ($providerId === '' || mb_strlen($providerId) > 255) {
            throw new GoogleCustomerAuthenticationException(
                'Google không cung cấp mã tài khoản hợp lệ.',
                'missing_provider_id'
            );
        }

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản Google phải cung cấp địa chỉ email hợp lệ.',
                'missing_or_invalid_email'
            );
        }

        if ($emailVerified !== true) {
            throw new GoogleCustomerAuthenticationException(
                'Email Google chưa được xác minh nên không thể đăng nhập.',
                'unverified_email'
            );
        }

        $name = trim((string) $googleUser->getName());
        $name = $name !== '' ? $name : Str::before($email, '@');

        return [
            'provider_id' => $providerId,
            'email' => $email,
            'name' => Str::limit($name, 255, ''),
        ];
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomerWithRaceRetry(array $identity): array
    {
        try {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        } catch (UniqueConstraintViolationException) {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        }
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomer(array $identity): array
    {
        $linkedUser = User::query()
            ->with('role')
            ->where('google_id', $identity['provider_id'])
            ->lockForUpdate()
            ->first();

        if ($linkedUser !== null) {
            $this->assertCustomerMayAuthenticate($linkedUser);

            return [$linkedUser, false];
        }

        $emailUser = User::query()
            ->with('role')
            ->where('email', $identity['email'])
            ->lockForUpdate()
            ->first();

        if ($emailUser !== null) {
            $this->assertCustomerMayAuthenticate($emailUser);

            if (filled($emailUser->google_id) && ! hash_equals((string) $emailUser->google_id, $identity['provider_id'])) {
                throw new GoogleCustomerAuthenticationException(
                    'Email này đã được liên kết với một tài khoản Google khác.',
                    'email_linked_to_different_google_account'
                );
            }

            $emailUser->google_id = $identity['provider_id'];
            $emailUser->email_verified_at ??= now();
            $emailUser->save();

            return [$emailUser, false];
        }

        $customerRole = Role::query()
            ->where('slug', 'user')
            ->lockForUpdate()
            ->first();

        if ($customerRole === null) {
            throw new GoogleCustomerAuthenticationException(
                'Không thể tạo tài khoản khách hàng lúc này.',
                'customer_role_missing'
            );
        }

        $user = new User;
        $user->name = $identity['name'];
        $user->email = $identity['email'];
        $user->google_id = $identity['provider_id'];
        $user->email_verified_at = now();
        $user->password = Hash::make(Str::random(64));
        $user->role_id = $customerRole->id;
        $user->status = 'active';
        $user->save();
        $user->setRelation('role', $customerRole);

        return [$user, true];
    }

    private function assertCustomerMayAuthenticate(User $user): void
    {
        if (! $user->isActive()) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản hiện không thể đăng nhập.',
                'inactive_customer'
            );
        }

        if (! $user->hasRole('user')) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản nhân sự không thể đăng nhập qua cổng Google dành cho khách hàng.',
                'privileged_account_collision'
            );
        }
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}

final class GoogleCustomerAuthenticationException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $event)
    {
        parent::__construct($message);
    }
}
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function redirect(): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()->route('login')->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            Log::notice('Google customer authentication was cancelled.', [
                'provider' => 'google',
                'event' => 'customer_cancelled',
            ]);

            return $this->failure('Đăng nhập Google đã bị hủy. Vui lòng thử lại nếu bạn muốn tiếp tục.');
        }

        if (! self::isConfigured()) {
            return $this->failure('Đăng nhập Google chưa được cấu hình.');
        }

        try {
            $identity = $this->verifiedIdentity(Socialite::driver('google')->user());
            [$user, $created] = $this->resolveCustomerWithRaceRetry($identity);
        } catch (GoogleCustomerAuthenticationException $exception) {
            Log::notice('Google customer authentication was rejected.', [
                'provider' => 'google',
                'event' => $exception->event,
            ]);

            return $this->failure($exception->getMessage());
        } catch (Throwable) {
            Log::warning('Google customer authentication failed.', [
                'provider' => 'google',
                'event' => 'provider_or_persistence_failure',
            ]);

            return $this->failure('Không thể đăng nhập bằng Google lúc này. Vui lòng thử lại.');
        }

        if ($created) {
            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * @return array{provider_id: string, email: string, name: string}
     */
    private function verifiedIdentity(SocialiteUser $googleUser): array
    {
        $providerId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawPayload = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];
        $raw = is_array($rawPayload) ? $rawPayload : [];
        $emailVerified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if ($providerId === '' || mb_strlen($providerId) > 255) {
            throw new GoogleCustomerAuthenticationException(
                'Google không cung cấp mã tài khoản hợp lệ.',
                'missing_provider_id'
            );
        }

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản Google phải cung cấp địa chỉ email hợp lệ.',
                'missing_or_invalid_email'
            );
        }

        if ($emailVerified !== true) {
            throw new GoogleCustomerAuthenticationException(
                'Email Google chưa được xác minh nên không thể đăng nhập.',
                'unverified_email'
            );
        }

        $name = trim((string) $googleUser->getName());
        $name = $name !== '' ? $name : Str::before($email, '@');

        return [
            'provider_id' => $providerId,
            'email' => $email,
            'name' => Str::limit($name, 255, ''),
        ];
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomerWithRaceRetry(array $identity): array
    {
        try {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        } catch (UniqueConstraintViolationException) {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        }
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomer(array $identity): array
    {
        $linkedUser = User::query()
            ->with('role')
            ->where('google_id', $identity['provider_id'])
            ->lockForUpdate()
            ->first();

        if ($linkedUser !== null) {
            $this->assertCustomerMayAuthenticate($linkedUser);

            return [$linkedUser, false];
        }

        $emailUser = User::query()
            ->with('role')
            ->where('email', $identity['email'])
            ->lockForUpdate()
            ->first();

        if ($emailUser !== null) {
            $this->assertCustomerMayAuthenticate($emailUser);

            if (filled($emailUser->google_id) && ! hash_equals((string) $emailUser->google_id, $identity['provider_id'])) {
                throw new GoogleCustomerAuthenticationException(
                    'Email này đã được liên kết với một tài khoản Google khác.',
                    'email_linked_to_different_google_account'
                );
            }

            $emailUser->google_id = $identity['provider_id'];
            $emailUser->email_verified_at ??= now();
            $emailUser->save();

            return [$emailUser, false];
        }

        $customerRole = Role::query()
            ->where('slug', 'user')
            ->lockForUpdate()
            ->first();

        if ($customerRole === null) {
            throw new GoogleCustomerAuthenticationException(
                'Không thể tạo tài khoản khách hàng lúc này.',
                'customer_role_missing'
            );
        }

        $user = new User;
        $user->name = $identity['name'];
        $user->email = $identity['email'];
        $user->google_id = $identity['provider_id'];
        $user->email_verified_at = now();
        $user->password = Hash::make(Str::random(64));
        $user->role_id = $customerRole->id;
        $user->status = 'active';
        $user->save();
        $user->setRelation('role', $customerRole);

        return [$user, true];
    }

    private function assertCustomerMayAuthenticate(User $user): void
    {
        if (! $user->isActive()) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản hiện không thể đăng nhập.',
                'inactive_customer'
            );
        }

        if (! $user->hasRole('user')) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản nhân sự không thể đăng nhập qua cổng Google dành cho khách hàng.',
                'privileged_account_collision'
            );
        }
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}

final class GoogleCustomerAuthenticationException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $event)
    {
        parent::__construct($message);
    }
}
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function redirect(): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()->route('login')->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            Log::notice('Google customer authentication was cancelled.', [
                'provider' => 'google',
                'event' => 'customer_cancelled',
            ]);

            return $this->failure('Đăng nhập Google đã bị hủy. Vui lòng thử lại nếu bạn muốn tiếp tục.');
        }

        if (! self::isConfigured()) {
            return $this->failure('Đăng nhập Google chưa được cấu hình.');
        }

        try {
            $identity = $this->verifiedIdentity(Socialite::driver('google')->user());
            [$user, $created] = $this->resolveCustomerWithRaceRetry($identity);
        } catch (GoogleCustomerAuthenticationException $exception) {
            Log::notice('Google customer authentication was rejected.', [
                'provider' => 'google',
                'event' => $exception->event,
            ]);

            return $this->failure($exception->getMessage());
        } catch (Throwable) {
            Log::warning('Google customer authentication failed.', [
                'provider' => 'google',
                'event' => 'provider_or_persistence_failure',
            ]);

            return $this->failure('Không thể đăng nhập bằng Google lúc này. Vui lòng thử lại.');
        }

        if ($created) {
            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * @return array{provider_id: string, email: string, name: string}
     */
    private function verifiedIdentity(SocialiteUser $googleUser): array
    {
        $providerId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawPayload = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];
        $raw = is_array($rawPayload) ? $rawPayload : [];
        $emailVerified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if ($providerId === '' || mb_strlen($providerId) > 255) {
            throw new GoogleCustomerAuthenticationException(
                'Google không cung cấp mã tài khoản hợp lệ.',
                'missing_provider_id'
            );
        }

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản Google phải cung cấp địa chỉ email hợp lệ.',
                'missing_or_invalid_email'
            );
        }

        if ($emailVerified !== true) {
            throw new GoogleCustomerAuthenticationException(
                'Email Google chưa được xác minh nên không thể đăng nhập.',
                'unverified_email'
            );
        }

        $name = trim((string) $googleUser->getName());
        $name = $name !== '' ? $name : Str::before($email, '@');

        return [
            'provider_id' => $providerId,
            'email' => $email,
            'name' => Str::limit($name, 255, ''),
        ];
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomerWithRaceRetry(array $identity): array
    {
        try {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        } catch (UniqueConstraintViolationException) {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        }
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomer(array $identity): array
    {
        $linkedUser = User::query()
            ->with('role')
            ->where('google_id', $identity['provider_id'])
            ->lockForUpdate()
            ->first();

        if ($linkedUser !== null) {
            $this->assertCustomerMayAuthenticate($linkedUser);

            return [$linkedUser, false];
        }

        $emailUser = User::query()
            ->with('role')
            ->where('email', $identity['email'])
            ->lockForUpdate()
            ->first();

        if ($emailUser !== null) {
            $this->assertCustomerMayAuthenticate($emailUser);

            if (filled($emailUser->google_id) && ! hash_equals((string) $emailUser->google_id, $identity['provider_id'])) {
                throw new GoogleCustomerAuthenticationException(
                    'Email này đã được liên kết với một tài khoản Google khác.',
                    'email_linked_to_different_google_account'
                );
            }

            $emailUser->google_id = $identity['provider_id'];
            $emailUser->email_verified_at ??= now();
            $emailUser->save();

            return [$emailUser, false];
        }

        $customerRole = Role::query()
            ->where('slug', 'user')
            ->lockForUpdate()
            ->first();

        if ($customerRole === null) {
            throw new GoogleCustomerAuthenticationException(
                'Không thể tạo tài khoản khách hàng lúc này.',
                'customer_role_missing'
            );
        }

        $user = new User;
        $user->name = $identity['name'];
        $user->email = $identity['email'];
        $user->google_id = $identity['provider_id'];
        $user->email_verified_at = now();
        $user->password = Hash::make(Str::random(64));
        $user->role_id = $customerRole->id;
        $user->status = 'active';
        $user->save();
        $user->setRelation('role', $customerRole);

        return [$user, true];
    }

    private function assertCustomerMayAuthenticate(User $user): void
    {
        if (! $user->isActive()) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản hiện không thể đăng nhập.',
                'inactive_customer'
            );
        }

        if (! $user->hasRole('user')) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản nhân sự không thể đăng nhập qua cổng Google dành cho khách hàng.',
                'privileged_account_collision'
            );
        }
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}

final class GoogleCustomerAuthenticationException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $event)
    {
        parent::__construct($message);
    }
}
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function redirect(): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()->route('login')->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            Log::notice('Google customer authentication was cancelled.', [
                'provider' => 'google',
                'event' => 'customer_cancelled',
            ]);

            return $this->failure('Đăng nhập Google đã bị hủy. Vui lòng thử lại nếu bạn muốn tiếp tục.');
        }

        if (! self::isConfigured()) {
            return $this->failure('Đăng nhập Google chưa được cấu hình.');
        }

        try {
            $identity = $this->verifiedIdentity(Socialite::driver('google')->user());
            [$user, $created] = $this->resolveCustomerWithRaceRetry($identity);
        } catch (GoogleCustomerAuthenticationException $exception) {
            Log::notice('Google customer authentication was rejected.', [
                'provider' => 'google',
                'event' => $exception->event,
            ]);

            return $this->failure($exception->getMessage());
        } catch (Throwable) {
            Log::warning('Google customer authentication failed.', [
                'provider' => 'google',
                'event' => 'provider_or_persistence_failure',
            ]);

            return $this->failure('Không thể đăng nhập bằng Google lúc này. Vui lòng thử lại.');
        }

        if ($created) {
            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * @return array{provider_id: string, email: string, name: string}
     */
    private function verifiedIdentity(SocialiteUser $googleUser): array
    {
        $providerId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawPayload = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];
        $raw = is_array($rawPayload) ? $rawPayload : [];
        $emailVerified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if ($providerId === '' || mb_strlen($providerId) > 255) {
            throw new GoogleCustomerAuthenticationException(
                'Google không cung cấp mã tài khoản hợp lệ.',
                'missing_provider_id'
            );
        }

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản Google phải cung cấp địa chỉ email hợp lệ.',
                'missing_or_invalid_email'
            );
        }

        if ($emailVerified !== true) {
            throw new GoogleCustomerAuthenticationException(
                'Email Google chưa được xác minh nên không thể đăng nhập.',
                'unverified_email'
            );
        }

        $name = trim((string) $googleUser->getName());
        $name = $name !== '' ? $name : Str::before($email, '@');

        return [
            'provider_id' => $providerId,
            'email' => $email,
            'name' => Str::limit($name, 255, ''),
        ];
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomerWithRaceRetry(array $identity): array
    {
        try {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        } catch (UniqueConstraintViolationException) {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        }
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomer(array $identity): array
    {
        $linkedUser = User::query()
            ->with('role')
            ->where('google_id', $identity['provider_id'])
            ->lockForUpdate()
            ->first();

        if ($linkedUser !== null) {
            $this->assertCustomerMayAuthenticate($linkedUser);

            return [$linkedUser, false];
        }

        $emailUser = User::query()
            ->with('role')
            ->where('email', $identity['email'])
            ->lockForUpdate()
            ->first();

        if ($emailUser !== null) {
            $this->assertCustomerMayAuthenticate($emailUser);

            if (filled($emailUser->google_id) && ! hash_equals((string) $emailUser->google_id, $identity['provider_id'])) {
                throw new GoogleCustomerAuthenticationException(
                    'Email này đã được liên kết với một tài khoản Google khác.',
                    'email_linked_to_different_google_account'
                );
            }

            $emailUser->google_id = $identity['provider_id'];
            $emailUser->email_verified_at ??= now();
            $emailUser->save();

            return [$emailUser, false];
        }

        $customerRole = Role::query()
            ->where('slug', 'user')
            ->lockForUpdate()
            ->first();

        if ($customerRole === null) {
            throw new GoogleCustomerAuthenticationException(
                'Không thể tạo tài khoản khách hàng lúc này.',
                'customer_role_missing'
            );
        }

        $user = new User;
        $user->name = $identity['name'];
        $user->email = $identity['email'];
        $user->google_id = $identity['provider_id'];
        $user->email_verified_at = now();
        $user->password = Hash::make(Str::random(64));
        $user->role_id = $customerRole->id;
        $user->status = 'active';
        $user->save();
        $user->setRelation('role', $customerRole);

        return [$user, true];
    }

    private function assertCustomerMayAuthenticate(User $user): void
    {
        if (! $user->isActive()) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản hiện không thể đăng nhập.',
                'inactive_customer'
            );
        }

        if (! $user->hasRole('user')) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản nhân sự không thể đăng nhập qua cổng Google dành cho khách hàng.',
                'privileged_account_collision'
            );
        }
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}

final class GoogleCustomerAuthenticationException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $event)
    {
        parent::__construct($message);
    }
}
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function redirect(): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()->route('login')->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            Log::notice('Google customer authentication was cancelled.', [
                'provider' => 'google',
                'event' => 'customer_cancelled',
            ]);

            return $this->failure('Đăng nhập Google đã bị hủy. Vui lòng thử lại nếu bạn muốn tiếp tục.');
        }

        if (! self::isConfigured()) {
            return $this->failure('Đăng nhập Google chưa được cấu hình.');
        }

        try {
            $identity = $this->verifiedIdentity(Socialite::driver('google')->user());
            [$user, $created] = $this->resolveCustomerWithRaceRetry($identity);
        } catch (GoogleCustomerAuthenticationException $exception) {
            Log::notice('Google customer authentication was rejected.', [
                'provider' => 'google',
                'event' => $exception->event,
            ]);

            return $this->failure($exception->getMessage());
        } catch (Throwable) {
            Log::warning('Google customer authentication failed.', [
                'provider' => 'google',
                'event' => 'provider_or_persistence_failure',
            ]);

            return $this->failure('Không thể đăng nhập bằng Google lúc này. Vui lòng thử lại.');
        }

        if ($created) {
            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * @return array{provider_id: string, email: string, name: string}
     */
    private function verifiedIdentity(SocialiteUser $googleUser): array
    {
        $providerId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawPayload = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];
        $raw = is_array($rawPayload) ? $rawPayload : [];
        $emailVerified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if ($providerId === '' || mb_strlen($providerId) > 255) {
            throw new GoogleCustomerAuthenticationException(
                'Google không cung cấp mã tài khoản hợp lệ.',
                'missing_provider_id'
            );
        }

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản Google phải cung cấp địa chỉ email hợp lệ.',
                'missing_or_invalid_email'
            );
        }

        if ($emailVerified !== true) {
            throw new GoogleCustomerAuthenticationException(
                'Email Google chưa được xác minh nên không thể đăng nhập.',
                'unverified_email'
            );
        }

        $name = trim((string) $googleUser->getName());
        $name = $name !== '' ? $name : Str::before($email, '@');

        return [
            'provider_id' => $providerId,
            'email' => $email,
            'name' => Str::limit($name, 255, ''),
        ];
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomerWithRaceRetry(array $identity): array
    {
        try {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        } catch (UniqueConstraintViolationException) {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        }
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomer(array $identity): array
    {
        $linkedUser = User::query()
            ->with('role')
            ->where('google_id', $identity['provider_id'])
            ->lockForUpdate()
            ->first();

        if ($linkedUser !== null) {
            $this->assertCustomerMayAuthenticate($linkedUser);

            return [$linkedUser, false];
        }

        $emailUser = User::query()
            ->with('role')
            ->where('email', $identity['email'])
            ->lockForUpdate()
            ->first();

        if ($emailUser !== null) {
            $this->assertCustomerMayAuthenticate($emailUser);

            if (filled($emailUser->google_id) && ! hash_equals((string) $emailUser->google_id, $identity['provider_id'])) {
                throw new GoogleCustomerAuthenticationException(
                    'Email này đã được liên kết với một tài khoản Google khác.',
                    'email_linked_to_different_google_account'
                );
            }

            $emailUser->google_id = $identity['provider_id'];
            $emailUser->email_verified_at ??= now();
            $emailUser->save();

            return [$emailUser, false];
        }

        $customerRole = Role::query()
            ->where('slug', 'user')
            ->lockForUpdate()
            ->first();

        if ($customerRole === null) {
            throw new GoogleCustomerAuthenticationException(
                'Không thể tạo tài khoản khách hàng lúc này.',
                'customer_role_missing'
            );
        }

        $user = new User;
        $user->name = $identity['name'];
        $user->email = $identity['email'];
        $user->google_id = $identity['provider_id'];
        $user->email_verified_at = now();
        $user->password = Hash::make(Str::random(64));
        $user->role_id = $customerRole->id;
        $user->status = 'active';
        $user->save();
        $user->setRelation('role', $customerRole);

        return [$user, true];
    }

    private function assertCustomerMayAuthenticate(User $user): void
    {
        if (! $user->isActive()) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản hiện không thể đăng nhập.',
                'inactive_customer'
            );
        }

        if (! $user->hasRole('user')) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản nhân sự không thể đăng nhập qua cổng Google dành cho khách hàng.',
                'privileged_account_collision'
            );
        }
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}

final class GoogleCustomerAuthenticationException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $event)
    {
        parent::__construct($message);
    }
}
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function redirect(): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()->route('login')->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            Log::notice('Google customer authentication was cancelled.', [
                'provider' => 'google',
                'event' => 'customer_cancelled',
            ]);

            return $this->failure('Đăng nhập Google đã bị hủy. Vui lòng thử lại nếu bạn muốn tiếp tục.');
        }

        if (! self::isConfigured()) {
            return $this->failure('Đăng nhập Google chưa được cấu hình.');
        }

        try {
            $identity = $this->verifiedIdentity(Socialite::driver('google')->user());
            [$user, $created] = $this->resolveCustomerWithRaceRetry($identity);
        } catch (GoogleCustomerAuthenticationException $exception) {
            Log::notice('Google customer authentication was rejected.', [
                'provider' => 'google',
                'event' => $exception->event,
            ]);

            return $this->failure($exception->getMessage());
        } catch (Throwable) {
            Log::warning('Google customer authentication failed.', [
                'provider' => 'google',
                'event' => 'provider_or_persistence_failure',
            ]);

            return $this->failure('Không thể đăng nhập bằng Google lúc này. Vui lòng thử lại.');
        }

        if ($created) {
            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * @return array{provider_id: string, email: string, name: string}
     */
    private function verifiedIdentity(SocialiteUser $googleUser): array
    {
        $providerId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawPayload = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];
        $raw = is_array($rawPayload) ? $rawPayload : [];
        $emailVerified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if ($providerId === '' || mb_strlen($providerId) > 255) {
            throw new GoogleCustomerAuthenticationException(
                'Google không cung cấp mã tài khoản hợp lệ.',
                'missing_provider_id'
            );
        }

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản Google phải cung cấp địa chỉ email hợp lệ.',
                'missing_or_invalid_email'
            );
        }

        if ($emailVerified !== true) {
            throw new GoogleCustomerAuthenticationException(
                'Email Google chưa được xác minh nên không thể đăng nhập.',
                'unverified_email'
            );
        }

        $name = trim((string) $googleUser->getName());
        $name = $name !== '' ? $name : Str::before($email, '@');

        return [
            'provider_id' => $providerId,
            'email' => $email,
            'name' => Str::limit($name, 255, ''),
        ];
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomerWithRaceRetry(array $identity): array
    {
        try {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        } catch (UniqueConstraintViolationException) {
            return DB::transaction(fn () => $this->resolveCustomer($identity), 3);
        }
    }

    /**
     * @param  array{provider_id: string, email: string, name: string}  $identity
     * @return array{0: User, 1: bool}
     */
    private function resolveCustomer(array $identity): array
    {
        $linkedUser = User::query()
            ->with('role')
            ->where('google_id', $identity['provider_id'])
            ->lockForUpdate()
            ->first();

        if ($linkedUser !== null) {
            $this->assertCustomerMayAuthenticate($linkedUser);

            return [$linkedUser, false];
        }

        $emailUser = User::query()
            ->with('role')
            ->where('email', $identity['email'])
            ->lockForUpdate()
            ->first();

        if ($emailUser !== null) {
            $this->assertCustomerMayAuthenticate($emailUser);

            if (filled($emailUser->google_id) && ! hash_equals((string) $emailUser->google_id, $identity['provider_id'])) {
                throw new GoogleCustomerAuthenticationException(
                    'Email này đã được liên kết với một tài khoản Google khác.',
                    'email_linked_to_different_google_account'
                );
            }

            $emailUser->google_id = $identity['provider_id'];
            $emailUser->email_verified_at ??= now();
            $emailUser->save();

            return [$emailUser, false];
        }

        $customerRole = Role::query()
            ->where('slug', 'user')
            ->lockForUpdate()
            ->first();

        if ($customerRole === null) {
            throw new GoogleCustomerAuthenticationException(
                'Không thể tạo tài khoản khách hàng lúc này.',
                'customer_role_missing'
            );
        }

        $user = new User;
        $user->name = $identity['name'];
        $user->email = $identity['email'];
        $user->google_id = $identity['provider_id'];
        $user->email_verified_at = now();
        $user->password = Hash::make(Str::random(64));
        $user->role_id = $customerRole->id;
        $user->status = 'active';
        $user->save();
        $user->setRelation('role', $customerRole);

        return [$user, true];
    }

    private function assertCustomerMayAuthenticate(User $user): void
    {
        if (! $user->isActive()) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản hiện không thể đăng nhập.',
                'inactive_customer'
            );
        }

        if (! $user->hasRole('user')) {
            throw new GoogleCustomerAuthenticationException(
                'Tài khoản nhân sự không thể đăng nhập qua cổng Google dành cho khách hàng.',
                'privileged_account_collision'
            );
        }
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}

final class GoogleCustomerAuthenticationException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $event)
    {
        parent::__construct($message);
    }
}
