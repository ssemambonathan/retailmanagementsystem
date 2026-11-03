<?php declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetToken extends Model
{
    use HasUuids;
    protected $keyType = 'string'; public $incrementing = false; public $timestamps = false;
    protected $fillable = ['user_id','token_hash','is_used','attempt_count','requested_from_ip','expires_at','used_at','used_from_ip','created_at'];
    protected function casts(): array { return ['is_used' => 'boolean','expires_at' => 'datetime','used_at' => 'datetime','created_at' => 'datetime']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function isValid(): bool   { return !$this->is_used && $this->expires_at->isFuture() && $this->attempt_count < 3; }
    public static function hash(string $token): string { return hash('sha256', $token); }

    public static function makeFor(User $user, string $ip): array
    {
        self::where('user_id', $user->id)->where('is_used', false)->update(['is_used' => true]);
        $raw = \Illuminate\Support\Str::random(64);
        $record = self::create(['user_id' => $user->id, 'token_hash' => self::hash($raw), 'requested_from_ip' => $ip, 'expires_at' => now()->addMinutes(60), 'created_at' => now()]);
        return ['token' => $raw, 'record' => $record];
    }
}
