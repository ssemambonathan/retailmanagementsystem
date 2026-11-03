<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();
    }

    private function makeUnverifiedUser(): User
    {
        $token = \Illuminate\Support\Str::random(64);
        $user  = User::factory()->create([
            'status'                     => 'active',
            'email_verified_at'          => null,
            'email_verification_token'   => $token,
            'email_verification_sent_at' => now(),
        ]);
        $user->assignRole('Customer');
        return $user;
    }

    public function test_valid_verification_link_verifies_email(): void
    {
        $user = $this->makeUnverifiedUser();

        $this->getJson("/api/v1/auth/email/verify/{$user->id}/{$user->email_verification_token}")
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id'                       => $user->id,
            'email_verification_token' => null,
        ]);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_invalid_token_rejected(): void
    {
        $user = $this->makeUnverifiedUser();

        $this->getJson("/api/v1/auth/email/verify/{$user->id}/wrong-token")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_TOKEN');
    }

    public function test_already_verified_email_is_idempotent(): void
    {
        $user = User::factory()->create([
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Customer');

        // Should not throw — just returns OK
        $this->getJson("/api/v1/auth/email/verify/{$user->id}/any-token")
            ->assertOk();
    }

    public function test_resend_verification_sends_email(): void
    {
        $user  = $this->makeUnverifiedUser();
        $token = auth('api')->login($user);

        $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend')
            ->assertOk();

        Notification::assertSentTo($user, \App\Notifications\VerifyEmailNotification::class);
    }

    public function test_resend_rejects_already_verified_user(): void
    {
        $user = User::factory()->create([
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Customer');
        $token = auth('api')->login($user);

        $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'ALREADY_VERIFIED');
    }

    public function test_protected_route_blocked_for_unverified_user(): void
    {
        $user  = $this->makeUnverifiedUser();
        $token = auth('api')->login($user);

        $this->withToken($token)
            ->getJson('/api/v1/portal/balance')
            ->assertForbidden()
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
    }
}
