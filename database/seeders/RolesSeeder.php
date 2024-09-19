<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

use App\Models\User;
use App\Models\SaleRep;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PerformanceNumber;
use App\Models\Setting;


class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {

        $view = Permission::create(['name' => 'view']);
        $create = Permission::create(['name' => 'create']);
        $edit = Permission::create(['name' => 'edit']);
        $delete = Permission::create(['name' => 'delete']);

        $admin_role = Role::create(['name' => 'admin']);
        $manager_role = Role::create(['name' => 'manager']);
        $salesreps_role = Role::create(['name' => 'salesreps']);
        $customer_role = Role::create(['name' => 'customer']);

        $admin_role->givePermissionTo([
            $view,
            $create,
            $edit,
            $delete
        ]);
        $manager_role->givePermissionTo([
            $view,
            $create,
            $edit,
        ]);
        $salesreps_role->givePermissionTo([
            $view,
            $create,
        ]);
        $customer_role->givePermissionTo([
            $view,
        ]);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $admin->assignRole($admin_role);

        $manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@gmail.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $manager->assignRole($manager_role);

        $salesreps = User::create([
            'name' => 'Sales Repsentative',
            'email' => 'salesreps@gmail.com',
            'password' => Hash::make('password'),
            'status' => 'active'
        ]);
        $salesreps->assignRole($salesreps_role);

        // $customer = User::create([
        //     'name'=> 'Customer',
        //     'email'=> 'customer@gmail.com',
        //     'password' => Hash::make('password'),
        // ]);
        // $customer->assignRole($customer_role);


        $salerep = SaleRep::create([
            'user_id' => 3,
            'username' => 'ikysr',
            'family_name' => 'Iky',
            'business_name' => 'NO SALE REP DEFAULT',
            'address' => '1234 Main St',
            'zip' => '12345',
            'city' => 'New York',
            'state' => 'NY',
            'mobile' => '1234567890',
            'paypal_account' => 'sb-liulr29976759@personal.example.com',
            'payment_method' => 1,
            'commission' => 10,
        ]);
        $PerformanceNumber = PerformanceNumber::create([
            'sales_rep_id' => $salesreps->id,
            'performance_number' => 10,

        ]);

        // $customerSeed = Customer::create([
        //     'user_id' => 4,
        //     'street' => '1234 Main St',
        //     'zipCode' => '12345',
        //     'country' => 'USA',
        //     'state' => 'NY',
        //     'city' => 'New York',
        //     'payment_method' => 3
        // ]);

        Setting::create([
            'key' => 'default_performance_number',
            'value' => '10',
            'created_by' => 1,
        ]);

        Setting::create([
            'key' => 'default_commission',
            'value' => '10',
            'created_by' => 1,
        ]);

        Setting::create([
            'key' => 'default_onboarding_period',
            'value' => '2',
            'created_by' => 1,
        ]);
    }
}
