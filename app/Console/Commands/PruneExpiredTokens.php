<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\JwtService;
use Illuminate\Console\Command;

/**
 * Scheduled cleanup of expired JWT blacklist entries and refresh tokens.
 *
 * Add to routes/console.php:
 *   Schedule::command('auth:prune-tokens')->hourly();
 *
 * Run manually:
 *   php artisan auth:prune-tokens
 */
class PruneExpiredTokens extends Command
{
    protected $signature   = 'auth:prune-tokens {--dry-run : Show counts without deleting}';
    protected $description = 'Remove expired JWT blacklist entries and refresh tokens';

    public function handle(JwtService $jwtService): int
    {
        $this->info('Pruning expired authentication tokens...');

        if ($this->option('dry-run')) {
            $blacklistCount  = \App\Models\JwtBlacklist::where('expires_at', '<', now())->count();
            $refreshCount    = \App\Models\RefreshToken::where('expires_at', '<', now())->count();
            $passwordCount   = \App\Models\PasswordResetToken::where('expires_at', '<', now())
                ->orWhere('is_used', true)->count();

            $this->table(
                ['Table', 'Rows to delete'],
                [
                    ['jwt_blacklist (expired)',           $blacklistCount],
                    ['refresh_tokens (expired)',          $refreshCount],
                    ['password_reset_tokens (expired/used)', $passwordCount],
                ]
            );

            $this->info('Dry run — no records deleted.');
            return self::SUCCESS;
        }

        $result = $jwtService->cleanupExpiredTokens();

        // Also clean up used/expired password reset tokens older than 24h
        $passwordPruned = \App\Models\PasswordResetToken::where(function ($q) {
            $q->where('expires_at', '<', now())
              ->orWhere(fn ($q2) => $q2->where('is_used', true)->where('created_at', '<', now()->subDay()));
        })->delete();

        $this->table(
            ['Table', 'Rows deleted'],
            [
                ['jwt_blacklist',       $result['blacklist_removed']],
                ['refresh_tokens',      $result['refresh_tokens_removed']],
                ['password_reset_tokens', $passwordPruned],
            ]
        );

        $this->info('Token cleanup complete.');

        return self::SUCCESS;
    }
}
