<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function loginAndGetTokens(): array
    {
        $user = User::factory()->create([
            'password'          => bcrypt('Valid@Pass1!'),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Customer');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Valid@Pass1!',
        ]);

        return [
            'user'          => $user,
            'access_token'  => $response->json('data.access_token'),
            'refresh_token' => $response->json('data.refresh_token'),
        ];
    }

    public function test_valid_refresh_token_returns_new_token_pair(): void
    {
        ['refresh_token' => $refreshToken] = $this->loginAndGetTokens();

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in'],
            ]);
    }

    public function test_refresh_invalidates_old_token(): void
    {
        ['refresh_token' => $refreshToken] = $this->loginAndGetTokens();

        // Rotate once
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken]);

        // Old token is now marked as used
        $hash = RefreshToken::hash($refreshToken);
        $this->assertDatabaseHas('refresh_tokens', [
            'token_hash' => $hash,
            'is_used'    => true,
        ]);
    }

    public function test_refresh_token_reuse_revokes_entire_family(): void
    {
        ['refresh_token' => $refreshToken, 'user' => $user] = $this->loginAndGetTokens();

        // First rotation — legitimate
        $rotateResponse = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);
        $newRefreshToken = $rotateResponse->json('data.refresh_token');

        // ATTACK: Present the old (already-used) refresh token
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ])->assertUnauthorized()
            ->assertJsonPath('code', 'TOKEN_REUSE_DETECTED');

        // All tokens in the family should be revoked
        $familyId = RefreshToken::where('token_hash', RefreshToken::hash($refreshToken))
            ->value('family_id');

        $allRevoked = RefreshToken::where('family_id', $familyId)
            ->where('is_revoked', false)
            ->doesntExist();

        $this->assertTrue($allRevoked, 'All tokens in the family should be revoked after reuse detection');

        // The new token from legitimate rotation should also be revoked
        $newHash = RefreshToken::hash($newRefreshToken);
        $this->assertDatabaseHas('refresh_tokens', [
            'token_hash' => $newHash,
            'is_revoked' => true,
        ]);

        // Audit log should record suspicious activity
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event'   => SecurityAuditLog::EVENT_SUSPICIOUS_ACTIVITY,
            'result'  => 'blocked',
        ]);
    }

    public function test_expired_refresh_token_returns_401(): void
    {
        $user = User::factory()->create([
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Customer');

        // Manually create an expired refresh token
        $raw = \Illuminate\Support\Str::random(64);
        RefreshToken::create([
            'user_id'    => $user->id,
            'token_hash' => RefreshToken::hash($raw),
            'family_id'  => \Illuminate\Support\Str::uuid(),
            'expires_at' => now()->subDay(),
        ]);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $raw])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_unknown_refresh_token_returns_401(): void
    {
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => str_repeat('a', 64),
        ])->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_refresh_requires_refresh_token_field(): void
    {
        $this->postJson('/api/v1/auth/refresh', [])
            ->assertUnprocessable();
    }
}
