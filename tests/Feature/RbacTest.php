<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function tokenFor(string $role): string
    {
        $user = User::factory()->create([
            'password'          => bcrypt('Valid@Pass1!'),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return auth('api')->login($user);
    }

    // ── Admin role ─────────────────────────────────────────────

    public function test_admin_can_access_admin_dashboard(): void
    {
        $token = $this->tokenFor('Admin');

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk();
    }

    public function test_admin_can_access_user_management(): void
    {
        $token = $this->tokenFor('Admin');

        $this->withToken($token)
            ->getJson('/api/v1/admin/users')
            ->assertOk();
    }

    public function test_admin_can_view_profits(): void
    {
        $token = $this->tokenFor('Admin');

        $this->withToken($token)
            ->getJson('/api/v1/admin/profits')
            ->assertOk();
    }

    public function test_admin_can_view_reports(): void
    {
        $token = $this->tokenFor('Admin');

        $this->withToken($token)
            ->getJson('/api/v1/admin/reports/daily')
            ->assertOk();
    }

    public function test_admin_can_manage_debts(): void
    {
        $token = $this->tokenFor('Admin');

        $this->withToken($token)
            ->getJson('/api/v1/admin/debts')
            ->assertOk();
    }

    // ── Shop Attendant role ────────────────────────────────────

    public function test_attendant_can_view_inventory(): void
    {
        $token = $this->tokenFor('Shop Attendant');

        $this->withToken($token)
            ->getJson('/api/v1/shop/products')
            ->assertOk();
    }

    public function test_attendant_can_record_sales(): void
    {
        $token = $this->tokenFor('Shop Attendant');

        $this->withToken($token)
            ->getJson('/api/v1/shop/sales')
            ->assertOk();
    }

    public function test_attendant_cannot_access_admin_routes(): void
    {
        $token = $this->tokenFor('Shop Attendant');

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'INSUFFICIENT_ROLE');
    }

    public function test_attendant_cannot_view_profits(): void
    {
        $token = $this->tokenFor('Shop Attendant');

        $this->withToken($token)
            ->getJson('/api/v1/admin/profits')
            ->assertForbidden();
    }

    public function test_attendant_cannot_manage_users(): void
    {
        $token = $this->tokenFor('Shop Attendant');

        $this->withToken($token)
            ->getJson('/api/v1/admin/users')
            ->assertForbidden();
    }

    // ── Customer role ──────────────────────────────────────────

    public function test_customer_can_view_debt_balance(): void
    {
        $token = $this->tokenFor('Customer');

        $this->withToken($token)
            ->getJson('/api/v1/portal/balance')
            ->assertOk();
    }

    public function test_customer_can_view_payment_history(): void
    {
        $token = $this->tokenFor('Customer');

        $this->withToken($token)
            ->getJson('/api/v1/portal/payments')
            ->assertOk();
    }

    public function test_customer_can_view_account_statement(): void
    {
        $token = $this->tokenFor('Customer');

        $this->withToken($token)
            ->getJson('/api/v1/portal/statement')
            ->assertOk();
    }

    public function test_customer_cannot_access_admin_routes(): void
    {
        $token = $this->tokenFor('Customer');

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }

    public function test_customer_cannot_access_shop_routes(): void
    {
        $token = $this->tokenFor('Customer');

        $this->withToken($token)
            ->getJson('/api/v1/shop/products')
            ->assertForbidden();
    }

    // ── Unauthenticated ────────────────────────────────────────

    public function test_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/v1/admin/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'TOKEN_ABSENT');
    }

    public function test_invalid_token_rejected(): void
    {
        $this->withToken('invalid.jwt.token')
            ->getJson('/api/v1/admin/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'TOKEN_INVALID');
    }

    // ── Token blacklist enforcement ────────────────────────────

    public function test_blacklisted_token_is_rejected(): void
    {
        $user = User::factory()->create([
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Admin');
        $token = auth('api')->login($user);

        // Logout (blacklists token)
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        // Token should now be rejected
        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'TOKEN_REVOKED');
    }

    // ── Password change invalidates tokens ─────────────────────

    public function test_token_invalidated_after_password_change(): void
    {
        $user = User::factory()->create([
            'password'          => bcrypt('OldPass@123!'),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Admin');
        $token = auth('api')->login($user);

        // Change password
        $this->withToken($token)->postJson('/api/v1/auth/password/change', [
            'current_password'          => 'OldPass@123!',
            'password'                  => 'NewPass@456!',
            'password_confirmation'     => 'NewPass@456!',
        ])->assertOk();

        // Old token should be rejected (ver claim mismatch)
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'TOKEN_REVOKED');
    }
}
