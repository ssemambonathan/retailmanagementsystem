<?php declare(strict_types=1);
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\{Permission, Role};

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage-users','manage-products','view-profits','manage-prices','view-reports','manage-debts',
            'record-sales','create-customer-accounts','record-payments','view-inventory',
            'view-debt-balance','view-payment-history','view-account-statement',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name'=>$p,'guard_name'=>'api']);
        }

        $admin = Role::firstOrCreate(['name'=>'Admin','guard_name'=>'api']);
        $admin->syncPermissions(array_merge($permissions)); // Admin gets all

        $attendant = Role::firstOrCreate(['name'=>'Shop Attendant','guard_name'=>'api']);
        $attendant->syncPermissions(['record-sales','create-customer-accounts','record-payments','view-inventory']);

        $customer = Role::firstOrCreate(['name'=>'Customer','guard_name'=>'api']);
        $customer->syncPermissions(['view-debt-balance','view-payment-history','view-account-statement']);

        Role::firstOrCreate(['name'=>'Super Admin','guard_name'=>'api']);

        $this->command->info('✓ Roles & permissions seeded');

        if (app()->environment(['local','staging'])) {
            $this->seedTestUsers();
        }
    }

    private function seedTestUsers(): void
    {
        $users = [
            ['email'=>'admin@shop.com',     'first_name'=>'System','last_name'=>'Admin',     'password'=>'Admin@12345!',     'role'=>'Admin'],
            ['email'=>'attendant@shop.com', 'first_name'=>'Jane',  'last_name'=>'Attendant', 'password'=>'Attend@12345!',   'role'=>'Shop Attendant'],
            ['email'=>'customer@shop.com',  'first_name'=>'John',  'last_name'=>'Customer',  'password'=>'Customer@12345!', 'role'=>'Customer'],
        ];
        foreach ($users as $data) {
            $role = $data['role']; unset($data['role']);
            $user = User::firstOrCreate(['email'=>$data['email']], array_merge($data, ['password'=>Hash::make($data['password']),'status'=>'active','email_verified_at'=>now()]));
            $user->syncRoles([$role]);
            $this->command->warn("  {$data['email']} / {$data['password']}");
        }
        $this->command->warn('⚠ Change all passwords before production!');
    }
}
