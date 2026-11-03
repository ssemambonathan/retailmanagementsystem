<?php declare(strict_types=1);
namespace App\Policies;
use App\Models\User;

class CustomerPolicy
{
    public function before(User $user, string $ability): ?bool { return $user->hasAnyRole(['Admin','Shop Attendant']) ? true : null; }
    public function viewDebtBalance(User $user, User $target): bool    { return $user->hasRole('Customer') && $user->hasPermissionTo('view-debt-balance') && $user->id === $target->id; }
    public function viewPaymentHistory(User $user, User $target): bool { return $user->hasRole('Customer') && $user->hasPermissionTo('view-payment-history') && $user->id === $target->id; }
    public function viewAccountStatement(User $user, User $target): bool { return $user->hasRole('Customer') && $user->hasPermissionTo('view-account-statement') && $user->id === $target->id; }
}
