<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SecurityAuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeVerifiedUser(array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'password'          => bcrypt('Valid@Pass1!'),
            'status'            => 'active',
            'email_verified_at' => now(),
        ], $attrs));
        $user->assignRole('Customer');
        return $user;
    }

    // ── Happy path ─────────────────────────────────────────────

    public function test_valid_credentials_return_token_pair(): void
    {
        $user = $this->makeVerifiedUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Valid@Pass1!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'status', 'data' => [
                    'access_token', 'refresh_token',
                    'token_type', 'expires_in', 'user',
                ],
            ])
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_login_resets_failed_login_count(): void
    {
        $user = $this->makeVerifiedUser(['failed_login_count' => 3]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Valid@Pass1!',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id'                 => $user->id,
            'failed_login_count' => 0,
        ]);
    }

    // ── Failed credentials ─────────────────────────────────────

    public function test_wrong_password_returns_401(): void
    {
        $user = $this->makeVerifiedUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'WrongPassword!',
        ])->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_nonexistent_email_returns_401(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'SomePassword1!',
        ])->assertUnauthorized();
    }

    public function test_wrong_password_increments_failed_count(): void
    {
        $user = $this->makeVerifiedUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $this->assertDatabaseHas('users', [
            'id'                 => $user->id,
            'failed_login_count' => 1,
        ]);
    }

    // ── Account lockout ────────────────────────────────────────

    public function test_account_locks_after_five_failed_attempts(): void
    {
        $user = $this->makeVerifiedUser();

        for ($i = 0; $i < User::MAX_LOGIN_ATTEMPTS; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => $user->email,
                'password' => 'WrongPassword!',
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'WrongPassword!',
        ])->assertStatus(423)
            ->assertJsonPath('code', 'ACCOUNT_LOCKED')
            ->assertJsonStructure(['data' => ['locked_until']]);
    }

    public function test_locked_account_rejects_correct_password(): void
    {
        $user = $this->makeVerifiedUser([
            'locked_until' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Valid@Pass1!',
        ])->assertStatus(423)
            ->assertJsonPath('code', 'ACCOUNT_LOCKED');
    }

    // ── Account status ─────────────────────────────────────────

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->makeVerifiedUser(['status' => 'inactive']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Valid@Pass1!',
        ])->assertUnauthorized();
    }

    public function test_unverified_email_blocks_login(): void
    {
        $user = User::factory()->create([
            'password'          => bcrypt('Valid@Pass1!'),
            'status'            => 'active',
            'email_verified_at' => null,
        ]);
        $user->assignRole('Customer');

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Valid@Pass1!',
        ])->assertStatus(403)
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
    }

    // ── Security audit log ─────────────────────────────────────

    public function test_successful_login_writes_audit_log(): void
    {
        $user = $this->makeVerifiedUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Valid@Pass1!',
        ])->assertOk();

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event'   => SecurityAuditLog::EVENT_LOGIN_SUCCESS,
            'result'  => 'success',
        ]);
    }

    public function test_failed_login_writes_audit_log(): void
    {
        $user = $this->makeVerifiedUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event'   => SecurityAuditLog::EVENT_LOGIN_FAILED,
            'result'  => 'failed',
        ]);
    }

    // ── Validation ─────────────────────────────────────────────

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'not-an-email',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    // ── Security headers ───────────────────────────────────────

    public function test_login_response_has_security_headers(): void
    {
        $user = $this->makeVerifiedUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Valid@Pass1!',
        ]);

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Cache-Control');
    }
}
