<?php declare(strict_types=1);
namespace App\Policies;
use App\Models\User;

class AttendantPolicy
{
    public function before(User $user, string $ability): ?bool { return $user->hasRole('Admin') ? true : null; }
    public function recordSales(User $user): bool             { return $user->hasRole('Shop Attendant') && $user->hasPermissionTo('record-sales'); }
    public function createCustomerAccounts(User $user): bool  { return $user->hasRole('Shop Attendant') && $user->hasPermissionTo('create-customer-accounts'); }
    public function recordPayments(User $user): bool          { return $user->hasRole('Shop Attendant') && $user->hasPermissionTo('record-payments'); }
    public function viewInventory(User $user): bool           { return $user->hasAnyRole(['Shop Attendant','Admin']) && $user->hasPermissionTo('view-inventory'); }
}
