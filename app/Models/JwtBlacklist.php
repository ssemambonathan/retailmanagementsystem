<?php declare(strict_types=1);
namespace App\Models;

use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class JwtBlacklist extends Model
{
    use HasUuids;
    protected $table = 'jwt_blacklist'; protected $keyType = 'string'; public $incrementing = false; const UPDATED_AT = null;
    protected $fillable = ['jti','user_id','token_type','reason','revoked_by','revoked_from_ip','expires_at'];
    protected function casts(): array { return ['expires_at' => 'datetime']; }

    protected static function boot(): void
    {
        parent::boot();
        static::updating(fn () => throw new ImmutableRecordException('JwtBlacklist'));
        static::deleting(fn () => throw new ImmutableRecordException('JwtBlacklist'));
    }

    public static function isRevoked(string $jti): bool
    {
        return Cache::remember("jwt_blacklist:{$jti}", 3600, fn () =>
            self::where('jti', $jti)->where('expires_at', '>', now())->exists()
        );
    }

    public static function revoke(string $jti, string $tokenType, \DateTimeInterface $expiresAt, ?string $userId = null, string $reason = 'logout', ?string $ip = null, ?string $revokedBy = null): self
    {
        $entry = self::create(['jti' => $jti, 'user_id' => $userId, 'token_type' => $tokenType, 'reason' => $reason, 'revoked_by' => $revokedBy, 'revoked_from_ip' => $ip, 'expires_at' => $expiresAt]);
        Cache::put("jwt_blacklist:{$jti}", true, $expiresAt);
        return $entry;
    }
}
