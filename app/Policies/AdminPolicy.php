<?php declare(strict_types=1);
namespace App\Policies;
use App\Models\User;

class AdminPolicy
{
    public function before(User $user, string $ability): ?bool { return $user->hasRole('Super Admin') ? true : null; }
    public function manageUsers(User $user): bool    { return $user->hasRole('Admin') && $user->hasPermissionTo('manage-users'); }
    public function manageProducts(User $user): bool { return $user->hasRole('Admin') && $user->hasPermissionTo('manage-products'); }
    public function viewProfits(User $user): bool    { return $user->hasRole('Admin') && $user->hasPermissionTo('view-profits'); }
    public function managePrices(User $user): bool   { return $user->hasRole('Admin') && $user->hasPermissionTo('manage-prices'); }
    public function viewReports(User $user): bool    { return $user->hasRole('Admin') && $user->hasPermissionTo('view-reports'); }
    public function manageDebts(User $user): bool    { return $user->hasRole('Admin') && $user->hasPermissionTo('manage-debts'); }
}
