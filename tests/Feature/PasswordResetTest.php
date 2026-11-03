<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PasswordResetToken;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();
    }

    private function makeUser(): User
    {
        $user = User::factory()->create([
            'password'          => bcrypt('OldPass@123!'),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Customer');
        return $user;
    }

    // ── Forgot password ────────────────────────────────────────

    public function test_forgot_password_always_returns_200(): void
    {
        // Known email
        $user = $this->makeUser();
        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
            ->assertOk();

        // Unknown email — same response (no enumeration)
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com'])
            ->assertOk();
    }

    public function test_forgot_password_sends_notification(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, \App\Notifications\PasswordResetNotification::class);
    }

    public function test_forgot_password_creates_reset_token(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertOk();

        $this->assertDatabaseHas('password_reset_tokens', [
            'user_id'  => $user->id,
            'is_used'  => false,
        ]);
    }

    // ── Reset password ─────────────────────────────────────────

    public function test_valid_token_resets_password(): void
    {
        $user = $this->makeUser();

        ['token' => $raw] = PasswordResetToken::makeFor($user, '127.0.0.1');

        $this->postJson('/api/v1/auth/password/reset', [
            'token'                 => $raw,
            'email'                 => $user->email,
            'password'              => 'NewSecure@789!',
            'password_confirmation' => 'NewSecure@789!',
        ])->assertOk();

        // Token should be marked as used
        $this->assertDatabaseHas('password_reset_tokens', [
            'user_id' => $user->id,
            'is_used' => true,
        ]);
    }

    public function test_reset_token_cannot_be_used_twice(): void
    {
        $user = $this->makeUser();
        ['token' => $raw] = PasswordResetToken::makeFor($user, '127.0.0.1');

        $payload = [
            'token'                 => $raw,
            'email'                 => $user->email,
            'password'              => 'NewSecure@789!',
            'password_confirmation' => 'NewSecure@789!',
        ];

        $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk();
        $this->postJson('/api/v1/auth/password/reset', $payload)->assertUnprocessable();
    }

    public function test_reset_rejects_same_password(): void
    {
        $user = $this->makeUser();
        ['token' => $raw] = PasswordResetToken::makeFor($user, '127.0.0.1');

        $this->postJson('/api/v1/auth/password/reset', [
            'token'                 => $raw,
            'email'                 => $user->email,
            'password'              => 'OldPass@123!',   // Same as current
            'password_confirmation' => 'OldPass@123!',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'PASSWORD_REUSE');
    }

    public function test_expired_reset_token_rejected(): void
    {
        $user = $this->makeUser();

        $raw = \Illuminate\Support\Str::random(64);
        PasswordResetToken::create([
            'user_id'    => $user->id,
            'token_hash' => PasswordResetToken::hash($raw),
            'expires_at' => now()->subHour(),
            'created_at' => now()->subHours(2),
        ]);

        $this->postJson('/api/v1/auth/password/reset', [
            'token'                 => $raw,
            'email'                 => $user->email,
            'password'              => 'NewSecure@789!',
            'password_confirmation' => 'NewSecure@789!',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_TOKEN');
    }

    // ── Change password (authenticated) ───────────────────────

    public function test_authenticated_user_can_change_password(): void
    {
        $user  = $this->makeUser();
        $token = auth('api')->login($user);

        $this->withToken($token)->postJson('/api/v1/auth/password/change', [
            'current_password'      => 'OldPass@123!',
            'password'              => 'NewPass@456!',
            'password_confirmation' => 'NewPass@456!',
        ])->assertOk();

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event'   => SecurityAuditLog::EVENT_PASSWORD_CHANGE,
            'result'  => 'success',
        ]);
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user  = $this->makeUser();
        $token = auth('api')->login($user);

        $this->withToken($token)->postJson('/api/v1/auth/password/change', [
            'current_password'      => 'WrongPassword!',
            'password'              => 'NewPass@456!',
            'password_confirmation' => 'NewPass@456!',
        ])->assertUnauthorized();
    }
}
