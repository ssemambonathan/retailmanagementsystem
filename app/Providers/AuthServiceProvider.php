<?php declare(strict_types=1);
namespace App\Providers;

use App\Models\User;
use App\Policies\{AdminPolicy, AttendantPolicy, CustomerPolicy};
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // Policies are accessed via Gate::define or $user->can() — not model-bound here
        // since Admin/Attendant/Customer policies govern role actions, not model ownership.
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Register admin ability gates
        \Illuminate\Support\Facades\Gate::define('manage-users',    [AdminPolicy::class, 'manageUsers']);
        \Illuminate\Support\Facades\Gate::define('manage-products', [AdminPolicy::class, 'manageProducts']);
        \Illuminate\Support\Facades\Gate::define('view-profits',    [AdminPolicy::class, 'viewProfits']);
        \Illuminate\Support\Facades\Gate::define('manage-prices',   [AdminPolicy::class, 'managePrices']);
        \Illuminate\Support\Facades\Gate::define('view-reports',    [AdminPolicy::class, 'viewReports']);
        \Illuminate\Support\Facades\Gate::define('manage-debts',    [AdminPolicy::class, 'manageDebts']);

        // Attendant gates
        \Illuminate\Support\Facades\Gate::define('record-sales',            [AttendantPolicy::class, 'recordSales']);
        \Illuminate\Support\Facades\Gate::define('create-customer-accounts',[AttendantPolicy::class, 'createCustomerAccounts']);
        \Illuminate\Support\Facades\Gate::define('record-payments',         [AttendantPolicy::class, 'recordPayments']);
        \Illuminate\Support\Facades\Gate::define('view-inventory',          [AttendantPolicy::class, 'viewInventory']);

        // Customer gates (ownership-scoped)
        \Illuminate\Support\Facades\Gate::define('view-debt-balance',     [CustomerPolicy::class, 'viewDebtBalance']);
        \Illuminate\Support\Facades\Gate::define('view-payment-history',  [CustomerPolicy::class, 'viewPaymentHistory']);
        \Illuminate\Support\Facades\Gate::define('view-account-statement',[CustomerPolicy::class, 'viewAccountStatement']);
    }
}
