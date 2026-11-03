<?php declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    use HasUuids;
    protected $keyType = 'string'; public $incrementing = false;

    protected $fillable = [
        'user_id','token_hash','family_id','is_used','is_revoked',
        'device_name','device_fingerprint','created_from_ip','user_agent',
        'expires_at','rotated_at','revoked_at','revoke_reason',
    ];

    protected function casts(): array
    {
        return ['is_used' => 'boolean','is_revoked' => 'boolean','expires_at' => 'datetime','rotated_at' => 'datetime','revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function isValid(): bool   { return !$this->is_used && !$this->is_revoked && $this->expires_at->isFuture(); }
    public static function hash(string $token): string { return hash('sha256', $token); }
}
