<?php declare(strict_types=1);
namespace App\Services;

use App\DTOs\Auth\{LoginDTO, RegisterDTO, ResetPasswordDTO};
use App\Exceptions\{AccountLockedException, AlreadyVerifiedException, EmailNotVerifiedException, InvalidTokenException, MustChangePasswordException, PasswordReuseException, TooManyRequestsException};
use App\Models\{PasswordResetToken, SecurityAuditLog, User};
use App\Notifications\{PasswordResetNotification, VerifyEmailNotification};
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Hash};

final readonly class AuthService
{
    public function __construct(private JwtService $jwtService) {}

    public function login(LoginDTO $dto, Request $request): array
    {
        $user = User::where('email', $dto->email)->first();

        if (!$user) {
            Hash::check($dto->password, '$2y$10$' . str_repeat('a', 53)); // timing attack prevention
            SecurityAuditLog::record(SecurityAuditLog::EVENT_LOGIN_FAILED, 'failed', ['email' => $dto->email, 'reason' => 'user_not_found']);
            throw new AuthenticationException('Invalid credentials.');
        }

        if ($user->is_locked) {
            SecurityAuditLog::record(SecurityAuditLog::EVENT_LOGIN_LOCKED, 'blocked', ['locked_until' => $user->locked_until->toIso8601String()], 'medium', $user->id);
            throw new AccountLockedException('Account locked. Try again in ' . (int) ceil(now()->diffInSeconds($user->locked_until) / 60) . ' minute(s).', $user->locked_until);
        }

        if ($user->status !== 'active') {
            throw new AuthenticationException('Account is not active. Contact support.');
        }

        if (!Hash::check($dto->password, $user->password)) {
            $user->incrementFailedLogins();
            $remaining = User::MAX_LOGIN_ATTEMPTS - $user->failed_login_count;
            SecurityAuditLog::record(SecurityAuditLog::EVENT_LOGIN_FAILED, 'failed', ['reason' => 'wrong_password', 'remaining_attempts' => max(0, $remaining)], null, $user->id);
            if ($remaining <= 0) {
                throw new AccountLockedException('Too many failed attempts. Account locked for ' . User::LOCKOUT_MINUTES . ' minutes.', now()->addMinutes(User::LOCKOUT_MINUTES));
            }
            throw new AuthenticationException("Invalid credentials. {$remaining} attempt(s) remaining.");
        }

        if (!$user->hasVerifiedEmail()) {
            throw new EmailNotVerifiedException('Please verify your email address before logging in.');
        }

        if ($user->must_change_password) {
            throw new MustChangePasswordException('You must change your password before continuing.');
        }

        $user->recordSuccessfulLogin($request->ip(), $request->userAgent() ?? '');
        $deviceName = $dto->deviceName ?? $this->parseDeviceName($request->userAgent() ?? '');
        $tokens     = $this->jwtService->issueTokenPair($user, $request, $deviceName);

        SecurityAuditLog::record(SecurityAuditLog::EVENT_LOGIN_SUCCESS, 'success', ['device' => $deviceName], null, $user->id);

        return array_merge($tokens, ['user' => $user->load('roles')]);
    }

    public function register(RegisterDTO $dto): User
    {
        $user = User::create([
            'first_name' => $dto->firstName, 'last_name' => $dto->lastName,
            'email'      => $dto->email,     'phone'     => $dto->phone,
            'password'   => Hash::make($dto->password),
            'status'     => 'active',
            'email_verification_token' => \Illuminate\Support\Str::random(64),
        ]);
        $user->assignRole($dto->role ?? 'Customer');
        $user->sendEmailVerificationNotification();
        return $user;
    }

    public function logout(User $user, string $accessToken, ?string $refreshToken = null): void
    {
        $this->jwtService->revokeAccessToken($accessToken, 'logout');
        if ($refreshToken) { $this->jwtService->revokeRefreshToken($refreshToken, 'logout'); }
        Cache::forget("email_verified:{$user->id}");
        SecurityAuditLog::record(SecurityAuditLog::EVENT_LOGOUT, 'success', [], null, $user->id);
    }

    public function logoutAll(User $user, string $accessToken): void
    {
        $this->jwtService->revokeAccessToken($accessToken, 'logout_all');
        $count = $this->jwtService->revokeAllRefreshTokens($user->id, 'logout_all');
        SecurityAuditLog::record(SecurityAuditLog::EVENT_LOGOUT, 'success', ['scope' => 'all_devices', 'sessions_revoked' => $count], null, $user->id);
    }

    public function verifyEmail(string $userId, string $token): void
    {
        $user = User::findOrFail($userId);
        if ($user->hasVerifiedEmail()) { return; }
        if (!$user->email_verification_token || !hash_equals($user->email_verification_token, $token)) {
            throw new InvalidTokenException('Invalid or expired verification token.');
        }
        $user->markEmailAsVerified();
        Cache::forget("email_verified:{$user->id}");
        SecurityAuditLog::record(SecurityAuditLog::EVENT_EMAIL_VERIFY, 'success', [], null, $user->id);
    }

    public function resendVerificationEmail(User $user): void
    {
        if ($user->hasVerifiedEmail()) { throw new AlreadyVerifiedException('Email is already verified.'); }
        $cacheKey = "verify_email_resend:{$user->id}";
        if (Cache::has($cacheKey)) { throw new TooManyRequestsException('Please wait before requesting another verification email.'); }
        Cache::put($cacheKey, true, 60);
        $user->update(['email_verification_token' => \Illuminate\Support\Str::random(64), 'email_verification_sent_at' => now()]);
        $user->sendEmailVerificationNotification();
        SecurityAuditLog::record(SecurityAuditLog::EVENT_EMAIL_VERIFY_RESEND, 'success', [], null, $user->id);
    }

    public function requestPasswordReset(string $email, string $ip): void
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            $cacheKey = "pwd_reset_request:{$user->id}";
            if (!Cache::has($cacheKey)) {
                Cache::put($cacheKey, 1, 600);
                ['token' => $raw] = PasswordResetToken::makeFor($user, $ip);
                $user->notify(new PasswordResetNotification($raw));
                SecurityAuditLog::record(SecurityAuditLog::EVENT_PASSWORD_RESET_REQUEST, 'success', ['ip' => $ip], null, $user->id);
            }
        }
    }

    public function resetPassword(ResetPasswordDTO $dto, string $ip): void
    {
        $hash  = PasswordResetToken::hash($dto->token);
        $reset = PasswordResetToken::where('token_hash', $hash)->where('is_used', false)->with('user')->first();

        if (!$reset) { throw new InvalidTokenException('Password reset token is invalid.'); }
        $reset->update(['attempt_count' => $reset->attempt_count + 1]);
        if (!$reset->isValid()) { throw new InvalidTokenException('Password reset token has expired or reached maximum attempts.'); }

        $user = $reset->user;
        if (Hash::check($dto->newPassword, $user->password)) { throw new PasswordReuseException('New password cannot be the same as your current password.'); }

        $user->update(['password' => Hash::make($dto->newPassword), 'password_changed_at' => now(), 'must_change_password' => false]);
        $reset->update(['is_used' => true, 'used_at' => now(), 'used_from_ip' => $ip]);
        $this->jwtService->revokeAllRefreshTokens($user->id, 'password_change');
        Cache::forget("email_verified:{$user->id}");
        SecurityAuditLog::record(SecurityAuditLog::EVENT_PASSWORD_DONE, 'success', ['ip' => $ip], null, $user->id);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword, string $accessToken): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            SecurityAuditLog::record(SecurityAuditLog::EVENT_PASSWORD_CHANGE, 'failed', ['reason' => 'wrong_current_password'], null, $user->id);
            throw new AuthenticationException('Current password is incorrect.');
        }
        if (Hash::check($newPassword, $user->password)) { throw new PasswordReuseException('New password cannot be the same as your current password.'); }
        $user->update(['password' => Hash::make($newPassword), 'password_changed_at' => now()]);
        $this->jwtService->revokeAccessToken($accessToken, 'password_change');
        $this->jwtService->revokeAllRefreshTokens($user->id, 'password_change');
        Cache::forget("email_verified:{$user->id}");
        SecurityAuditLog::record(SecurityAuditLog::EVENT_PASSWORD_CHANGE, 'success', [], null, $user->id);
    }

    private function parseDeviceName(string $ua): string
    {
        if (str_contains($ua, 'Mobile'))  return 'Mobile';
        if (str_contains($ua, 'Chrome'))  return 'Chrome Browser';
        if (str_contains($ua, 'Firefox')) return 'Firefox Browser';
        if (str_contains($ua, 'Safari'))  return 'Safari Browser';
        if (str_contains($ua, 'Postman')) return 'Postman';
        return 'Unknown Device';
    }
}
