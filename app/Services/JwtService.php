<?php declare(strict_types=1);
namespace App\Services;

use App\Exceptions\{InvalidRefreshTokenException, TokenReusedException, TokenRevokedException, EmailNotVerifiedException};
use App\Models\{JwtBlacklist, RefreshToken, SecurityAuditLog, User};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

final readonly class JwtService
{
    private const ACCESS_TTL_MINUTES = 15;
    private const REFRESH_TTL_DAYS   = 7;

    public function issueTokenPair(User $user, Request $request, string $deviceName = 'unknown'): array
    {
        $accessToken  = $this->issueAccessToken($user);
        $refreshToken = $this->issueRefreshToken($user, $request, Str::uuid()->toString(), $deviceName);
        return ['access_token' => $accessToken, 'refresh_token' => $refreshToken, 'token_type' => 'Bearer', 'expires_in' => self::ACCESS_TTL_MINUTES * 60, 'refresh_expires_in' => self::REFRESH_TTL_DAYS * 86400];
    }

    public function rotateRefreshToken(string $rawRefreshToken, Request $request): array
    {
        $hash        = RefreshToken::hash($rawRefreshToken);
        $storedToken = RefreshToken::where('token_hash', $hash)->first();

        if (!$storedToken) {
            SecurityAuditLog::record(SecurityAuditLog::EVENT_SUSPICIOUS_ACTIVITY, 'failed', ['reason' => 'Unknown refresh token'], 'high');
            throw new InvalidRefreshTokenException('Invalid refresh token.');
        }

        if ($storedToken->is_used || $storedToken->is_revoked) {
            $this->revokeFamilyTokens($storedToken->family_id, $storedToken->user_id, 'reuse_detected');
            SecurityAuditLog::record(SecurityAuditLog::EVENT_SUSPICIOUS_ACTIVITY, 'blocked', ['reason' => 'Token reuse detected', 'family_id' => $storedToken->family_id], 'critical', $storedToken->user_id);
            throw new TokenReusedException('Token reuse detected. All sessions revoked.');
        }

        if (!$storedToken->isValid()) {
            throw new InvalidRefreshTokenException('Refresh token has expired.');
        }

        $storedToken->update(['is_used' => true, 'rotated_at' => now()]);
        $user         = $storedToken->user;
        $accessToken  = $this->issueAccessToken($user);
        $refreshToken = $this->issueRefreshToken($user, $request, $storedToken->family_id, $storedToken->device_name ?? 'unknown');

        SecurityAuditLog::record(SecurityAuditLog::EVENT_TOKEN_REFRESH, 'success', [], null, $user->id);

        return ['access_token' => $accessToken, 'refresh_token' => $refreshToken, 'token_type' => 'Bearer', 'expires_in' => self::ACCESS_TTL_MINUTES * 60, 'refresh_expires_in' => self::REFRESH_TTL_DAYS * 86400];
    }

    public function validateAccessToken(string $token): User
    {
        $payload = JWTAuth::setToken($token)->getPayload();
        $jti     = $payload->get('jti');
        $userId  = $payload->get('sub');
        $ver     = $payload->get('ver');

        if (JwtBlacklist::isRevoked($jti)) {
            throw new TokenRevokedException('Token has been revoked.');
        }

        $user = User::find($userId);
        if (!$user || $user->status !== 'active') {
            throw new TokenRevokedException('User account is inactive or not found.');
        }

        if ($ver !== $user->tokenVersion()) {
            throw new TokenRevokedException('Token invalidated by password change.');
        }

        if (!Cache::remember("email_verified:{$user->id}", 3600, fn () => $user->hasVerifiedEmail())) {
            throw new EmailNotVerifiedException('Email address not verified.');
        }

        return $user;
    }

    public function revokeAccessToken(string $token, string $reason = 'logout', ?string $revokedBy = null): void
    {
        try {
            $payload   = JWTAuth::setToken($token)->getPayload();
            $jti       = $payload->get('jti');
            $expiresAt = Carbon::createFromTimestamp($payload->get('exp'));
            $userId    = $payload->get('sub');
        } catch (JWTException) { return; }

        JwtBlacklist::revoke($jti, 'access', $expiresAt, $userId, $reason, app('request')->ip(), $revokedBy);
    }

    public function revokeAllRefreshTokens(string $userId, string $reason = 'logout'): int
    {
        return RefreshToken::where('user_id', $userId)->where('is_revoked', false)->update(['is_revoked' => true, 'revoked_at' => now(), 'revoke_reason' => $reason]);
    }

    public function revokeRefreshToken(string $rawToken, string $reason = 'logout'): bool
    {
        return (bool) RefreshToken::where('token_hash', RefreshToken::hash($rawToken))->update(['is_revoked' => true, 'revoked_at' => now(), 'revoke_reason' => $reason]);
    }

    public function cleanupExpiredTokens(): array
    {
        return ['blacklist_removed' => JwtBlacklist::where('expires_at', '<', now())->delete(), 'refresh_tokens_removed' => RefreshToken::where('expires_at', '<', now())->delete()];
    }

    private function issueAccessToken(User $user): string
    {
        return JWTAuth::customClaims(['jti' => Str::random(32), 'ver' => $user->tokenVersion()])->fromUser($user);
    }

    private function issueRefreshToken(User $user, Request $request, string $familyId, string $deviceName): string
    {
        $raw = Str::random(64);
        RefreshToken::create(['user_id' => $user->id, 'token_hash' => RefreshToken::hash($raw), 'family_id' => $familyId, 'device_name' => $deviceName, 'created_from_ip' => $request->ip(), 'user_agent' => substr($request->userAgent() ?? '', 0, 300), 'expires_at' => now()->addDays(self::REFRESH_TTL_DAYS)]);
        return $raw;
    }

    private function revokeFamilyTokens(string $familyId, string $userId, string $reason): void
    {
        RefreshToken::where('family_id', $familyId)->update(['is_revoked' => true, 'revoked_at' => now(), 'revoke_reason' => $reason]);
    }
}
