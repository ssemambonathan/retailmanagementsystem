<?php declare(strict_types=1);
namespace App\Models;

use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class SecurityAuditLog extends Model
{
    use HasUuids;
    protected $table = 'security_audit_logs'; protected $keyType = 'string'; public $incrementing = false; const UPDATED_AT = null;

    const EVENT_LOGIN_SUCCESS          = 'login_success';
    const EVENT_LOGIN_FAILED           = 'login_failed';
    const EVENT_LOGIN_LOCKED           = 'login_locked';
    const EVENT_LOGOUT                 = 'logout';
    const EVENT_TOKEN_REFRESH          = 'token_refresh';
    const EVENT_TOKEN_REVOKE           = 'token_revoke';
    const EVENT_PASSWORD_CHANGE        = 'password_change';
    const EVENT_PASSWORD_RESET_REQUEST = 'password_reset_request';
    const EVENT_PASSWORD_DONE          = 'password_reset_complete';
    const EVENT_EMAIL_VERIFY           = 'email_verify';
    const EVENT_EMAIL_VERIFY_RESEND    = 'email_verify_resend';
    const EVENT_PERMISSION_DENIED      = 'permission_denied';
    const EVENT_SUSPICIOUS_ACTIVITY    = 'suspicious_activity';

    protected $fillable = ['user_id','event','result','ip_address','user_agent','country_code','metadata','risk_score'];
    protected function casts(): array { return ['metadata' => 'array','created_at' => 'datetime']; }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->created_at = $m->created_at ?? now());
        static::updating(fn () => throw new ImmutableRecordException('SecurityAuditLog'));
        static::deleting(fn () => throw new ImmutableRecordException('SecurityAuditLog'));
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function record(string $event, string $result = 'success', array $metadata = [], ?string $riskScore = null, ?string $userId = null): void
    {
        $request = app('request');
        unset($metadata['password'], $metadata['token'], $metadata['secret']);
        self::create([
            'user_id'    => $userId ?? Auth::id(),
            'event'      => $event,
            'result'     => $result,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr($request->userAgent() ?? '', 0, 400) : null,
            'metadata'   => $metadata ?: null,
            'risk_score' => $riskScore,
        ]);
    }
}
