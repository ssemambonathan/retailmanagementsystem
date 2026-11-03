<?php declare(strict_types=1);
namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory, HasUuids, HasRoles, Notifiable, SoftDeletes;

    protected $keyType   = 'string';
    public $incrementing = false;
    protected $guard_name = 'api';

    public const MAX_LOGIN_ATTEMPTS = 5;
    public const LOCKOUT_MINUTES    = 15;

    protected $fillable = [
        'first_name','last_name','email','phone','password','status',
        'email_verified_at','email_verification_token','email_verification_sent_at',
        'failed_login_count','locked_until','last_failed_at',
        'password_changed_at','must_change_password',
        'last_login_at','last_login_ip','last_login_user_agent',
        'two_factor_secret','two_factor_recovery_codes','two_factor_confirmed_at',
    ];

    protected $hidden = ['password','remember_token','two_factor_secret','two_factor_recovery_codes','email_verification_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'          => 'datetime',
            'email_verification_sent_at' => 'datetime',
            'locked_until'               => 'datetime',
            'last_failed_at'             => 'datetime',
            'password_changed_at'        => 'datetime',
            'last_login_at'              => 'datetime',
            'two_factor_confirmed_at'    => 'datetime',
            'password'                   => 'hashed',
            'must_change_password'       => 'boolean',
        ];
    }

    // JWT
    public function getJWTIdentifier(): mixed { return $this->getKey(); }
    public function getJWTCustomClaims(): array
    {
        return ['role' => $this->getRoleNames()->first(), 'ver' => $this->tokenVersion(), 'verified' => $this->hasVerifiedEmail()];
    }
    public function tokenVersion(): string
    {
        return substr(md5($this->password . ($this->password_changed_at?->timestamp ?? '0')), 0, 8);
    }

    // Relationships
    public function refreshTokens(): HasMany { return $this->hasMany(RefreshToken::class); }
    public function securityLogs(): HasMany  { return $this->hasMany(SecurityAuditLog::class); }
    public function passwordResets(): HasMany { return $this->hasMany(PasswordResetToken::class); }

    // Computed
    public function getFullNameAttribute(): string { return "{$this->first_name} {$this->last_name}"; }
    public function getIsLockedAttribute(): bool   { return $this->locked_until !== null && $this->locked_until->isFuture(); }

    // Brute-force
    public function incrementFailedLogins(): void
    {
        $newCount = $this->failed_login_count + 1;
        $updates  = ['failed_login_count' => $newCount, 'last_failed_at' => now()];
        if ($newCount >= self::MAX_LOGIN_ATTEMPTS) {
            $updates['locked_until'] = now()->addMinutes(self::LOCKOUT_MINUTES);
        }
        $this->update($updates);
    }
    public function resetFailedLogins(): void
    {
        $this->update(['failed_login_count' => 0, 'locked_until' => null, 'last_failed_at' => null]);
    }
    public function recordSuccessfulLogin(string $ip, string $ua): void
    {
        $this->update(['last_login_at' => now(), 'last_login_ip' => $ip, 'last_login_user_agent' => substr($ua, 0, 200)]);
        $this->resetFailedLogins();
    }

    // MustVerifyEmail
    public function hasVerifiedEmail(): bool { return $this->email_verified_at !== null; }
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill(['email_verified_at' => now(), 'email_verification_token' => null, 'email_verification_sent_at' => null])->save();
    }
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification());
    }

    // Scopes
    public function scopeActive($q) { return $q->where('status', 'active'); }
    public function scopeVerified($q) { return $q->whereNotNull('email_verified_at'); }
}
