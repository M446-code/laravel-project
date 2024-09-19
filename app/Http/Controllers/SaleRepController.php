<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Commission;
use App\Models\Invoice;
use App\Models\InvoiceSr;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Payout;
use Carbon\Carbon;
use App\Models\PerformanceNumber;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use App\Models\SaleRep;
use App\Models\Setting;
use App\Models\Subscription;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SaleRepController extends Controller
{

    public function getAllSalesReps()
    {
        $sale_reps = DB::table('sale_reps')
            ->join('users', 'sale_reps.user_id', '=', 'users.id')
            ->leftJoin('performance_numbers', 'sale_reps.user_id', '=', 'performance_numbers.sales_rep_id')
            ->select(
                'sale_reps.id',
                'sale_reps.user_id as user_id',
                'users.name as name',
                'users.email as email',
                'users.phone as phone',
                'users.status as status',
                'sale_reps.username',
                'sale_reps.family_name',
                'sale_reps.business_name',
                'sale_reps.address',
                'sale_reps.zip',
                'sale_reps.city',
                'sale_reps.state',
                'sale_reps.paypal_account',
                'sale_reps.photo_path',
                'sale_reps.id_card_front_path',
                'sale_reps.id_card_back_path',
                'sale_reps.form_1099_path',
                'sale_reps.i9_path',
                'sale_reps.w9_path',
                'sale_reps.payment_method',
                'sale_reps.commission',
                'sale_reps.created_at',
                'sale_reps.updated_at',
                'performance_numbers.performance_number as performance_number'
            )
            ->get();

        return $sale_reps;
    }

    public function getSingleSalesReps($user_id)
    {
        $saleRep = SaleRep::with('user', 'performanceNumbers')->where('user_id', $user_id)->first();

        if (!$saleRep) {
            return response()->json(['message' => 'Sales Representative not found'], 404);
        }

        $user = $saleRep->user;

        // Get the first performance number associated with the SaleRep
        $performanceNumber = $saleRep->performanceNumbers->first();
        $performanceNumberValue = $performanceNumber ? $performanceNumber->performance_number : null;

        $selerepData = [
            'id' => $saleRep->id,
            'user_id' => $saleRep->user_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'username' => $saleRep->username,
            'family_name' => $saleRep->family_name,
            'business_name' => $saleRep->business_name,
            'address' => $saleRep->address,
            'zip' => $saleRep->zip,
            'city' => $saleRep->city,
            'state' => $saleRep->state,
            'paypal_account' => $saleRep->paypal_account,
            'photo_path' => $saleRep->photo_path,
            'id_card_front_path' => $saleRep->id_card_front_path,
            'id_card_back_path' => $saleRep->id_card_back_path,
            'form_1099_path' => $saleRep->form_1099_path,
            'i9_path' => $saleRep->i9_path,
            'w9_path' => $saleRep->w9_path,
            'payment_method' => $saleRep->payment_method,
            'commission' => $saleRep->commission,
            'created_at' => $saleRep->created_at,
            'updated_at' => $saleRep->updated_at,
            'performance_numbers' => $performanceNumberValue,
        ];

        return response()->json(['salesrep' => $selerepData]);
    }

    public function getCustomeAllSalesReps(Request $request)
    {
        $perPage = $request->input('perPage', 500);
        $startDate = $request->input('startDate', '2020-01-01');
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay()->toDateString();

        $sale_reps = DB::table('sale_reps')
            ->join('users', 'sale_reps.user_id', '=', 'users.id')
            ->leftJoin('performance_numbers', 'sale_reps.user_id', '=', 'performance_numbers.sales_rep_id')
            ->whereBetween('sale_reps.created_at', [$startDate, $endDate])
            ->select(
                'sale_reps.id',
                'sale_reps.user_id as user_id',
                'users.name as name',
                'users.email as email',
                'users.phone as phone',
                'users.status as status',
                'sale_reps.username',
                'sale_reps.family_name',
                'sale_reps.business_name',
                'sale_reps.address',
                'sale_reps.zip',
                'sale_reps.city',
                'sale_reps.state',
                'sale_reps.paypal_account',
                'sale_reps.photo_path',
                'sale_reps.id_card_front_path',
                'sale_reps.id_card_back_path',
                'sale_reps.form_1099_path',
                'sale_reps.i9_path',
                'sale_reps.w9_path',
                'sale_reps.payment_method',
                'sale_reps.commission',
                'sale_reps.created_at',
                'sale_reps.updated_at',
                'performance_numbers.performance_number as performance_number'
            )
            // ->whereBetween('sale_reps.created_at', [$startDate, $endDate])
            ->orderBy('sale_reps.created_at', 'desc')
            ->paginate($perPage);


        // Loop through each sales rep and get additional data
        foreach ($sale_reps as $salesRep) {

            // Get Invoice information for the sales rep
            $invoices = InvoiceSr::where('role', 'salesreps')->where('role_user_id', $salesRep->user_id)->get();

            // Add the Invoice information to each customer object
            $salesRep->invoices = $invoices;


            // Get new customers associated with the sales rep within the specified period
            $newCustomers = Customer::where('referral_username', $salesRep->username)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // Add the new customers to each sales rep object
            $salesRep->new_customers = $newCustomers;

            $distinctCustomersBeforePeriod = Customer::where('referral_username', $salesRep->username)
                ->where('created_at', '<', $startDate)
                ->pluck('user_id')
                ->toArray();

            // Get the recurring customers within the specified period
            $recurringCustomers = Payment::whereIn('customer_id', $distinctCustomersBeforePeriod)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            // Count the number of recurring customers for each customer separately
            $recurringCustomersCount = $recurringCustomers->groupBy('customer_id')->count();

            // Add the count to each sales rep object
            $salesRep->recurring_customers_count = $recurringCustomersCount;

            $allCustomersBeforePeriod = Customer::where('referral_username', $salesRep->username)
                ->where('created_at', '<', $startDate)
                ->pluck('user_id')
                ->toArray();

            $commissionEarnedBeforePeriod = Commission::whereIn('customer_id', $allCustomersBeforePeriod)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $totalCommissionBeforePeriod = $commissionEarnedBeforePeriod->sum('commission_amount');

            $currentPeriodCustomers = Customer::where('referral_username', $salesRep->username)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->pluck('user_id')
                ->toArray();

            $commissionEarnedCurrentPeriod = Commission::whereIn('customer_id', $currentPeriodCustomers)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $totalCommissionCurrentPeriod = $commissionEarnedCurrentPeriod->sum('commission_amount');


            // Add the total commission to each sales rep object
            $salesRep->total_commission_earned = $totalCommissionBeforePeriod + $totalCommissionCurrentPeriod;

            // Get commission information for the sales representative
            $Commissionpayed = Commission::where('sales_rep_id', $salesRep->user_id)->where('paid', 1)
                ->whereBetween('created_at', [$startDate, $endDate])->get()->sum('commission_amount');

            // Add the commission information to each sales rep object
            $salesRep->total_commission_payed = $Commissionpayed;

            $commissionEarnedInFuture = Commission::where('sales_rep_id', $salesRep->user_id)
                ->groupBy('customer_id')
                ->pluck('customer_id')
                ->toArray();

            // $salesRep->total_commissionEarnedInFuture_customer_id = $commissionEarnedInFuture;


            // Create an instance of the Customer model
            $customerModel = new Customer();

            // Initialize an array to store the detailed information for each customer
            $detailedCustomerInfo = [];

            foreach ($commissionEarnedInFuture as $customerId) {
                // Retrieve customer information for the current customer ID
                $customerInfoCollection = $customerModel->getRecurringPaymentsPendingByCustomerId([$customerId]);

                // Check if the collection is not empty before trying to access its first item
                if (!$customerInfoCollection->isEmpty()) {
                    $customerInfo = $customerInfoCollection->first();

                    // Build the detailed customer information array and store it in $detailedCustomerInfo
                    $detailedCustomerInfo[] = [
                        'customer_id' => $customerId,
                        'referral_username' => $customerInfo->referral_username,
                        'total_recurring_payments_pending' => $customerInfo->total_recurring_payments_pending,
                    ];
                }
            }

            // Assign the detailed customer information array to $salesRep->total_commissionEarnedInFuture
            // $salesRep->total_commissionEarnedInFuture = $detailedCustomerInfo;

            $totalRecurringPayments = 0;

            foreach ($detailedCustomerInfo as $customerInfo) {
                $totalRecurringPayments += floatval($customerInfo['total_recurring_payments_pending']);
            }

            // Now, $totalRecurringPayments contains the sum of total_recurring_payments_pending values
            // $salesRep->total_recurring_payments = $totalRecurringPayments;

            // Calculate the commission to be earned based on the formula
            $commissiontobeearnedfuture = $totalRecurringPayments * ($salesRep->commission / 100);

            // Add the calculated commission to the sales rep object
            $salesRep->commission_to_be_earned_future = $commissiontobeearnedfuture;
        }

        return $sale_reps;
    }

    public function getCustomeSingleSalesReps($user_id, Request $request)
    {
        $startDate = $request->input('startDate', Carbon::now()->subDays(7)->toDateString());
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay()->toDateString();

        // Get the single sales rep based on the user_id
        $salesRep = SaleRep::join('users', 'sale_reps.user_id', '=', 'users.id')
            ->leftJoin('performance_numbers', 'sale_reps.user_id', '=', 'performance_numbers.sales_rep_id')
            ->select(
                'sale_reps.id',
                'sale_reps.user_id as user_id',
                'users.name as name',
                'users.email as email',
                'users.phone as phone',
                'users.status as status',
                'sale_reps.username',
                'sale_reps.family_name',
                'sale_reps.business_name',
                'sale_reps.address',
                'sale_reps.zip',
                'sale_reps.city',
                'sale_reps.state',
                'sale_reps.paypal_account',
                'sale_reps.photo_path',
                'sale_reps.id_card_front_path',
                'sale_reps.id_card_back_path',
                'sale_reps.form_1099_path',
                'sale_reps.i9_path',
                'sale_reps.w9_path',
                'sale_reps.payment_method',
                'sale_reps.commission as default_commission',
                'sale_reps.created_at',
                'sale_reps.updated_at',
                'performance_numbers.performance_number as performance_number'
            )
            ->where('sale_reps.user_id', $user_id)
            ->first();

        if (!$salesRep) {
            // Handle the case where the sales rep is not found
            return response()->json(['error' => 'Sales rep not found'], 404);
        }

        // Get additional data for the single sales rep
        $invoices = InvoiceSr::where('role', 'salesreps')->where('role_user_id', $salesRep->user_id)->get();
        $salesRep->invoices = $invoices;

        $newCustomers = Customer::where('referral_username', $salesRep->username)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $salesRep->new_customers = $newCustomers;

        $distinctCustomersBeforePeriod = Customer::where('referral_username', $salesRep->username)
            ->where('created_at', '<', $startDate)
            ->pluck('user_id')
            ->toArray();

        $recurringCustomers = Payment::whereIn('customer_id', $distinctCustomersBeforePeriod)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $recurringCustomersCount = $recurringCustomers->groupBy('customer_id')->count();
        $salesRep->recurring_customers_count = $recurringCustomersCount;


        $allCustomersBeforePeriod = Customer::where('referral_username', $salesRep->username)
            ->where('created_at', '<', $startDate)
            ->pluck('user_id')
            ->toArray();

        $commissionEarnedBeforePeriod = Commission::whereIn('customer_id', $allCustomersBeforePeriod)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalCommissionBeforePeriod = $commissionEarnedBeforePeriod->sum('commission_amount');

        $currentPeriodCustomers = Customer::where('referral_username', $salesRep->username)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->pluck('user_id')
            ->toArray();

        $commissionEarnedCurrentPeriod = Commission::whereIn('customer_id', $currentPeriodCustomers)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalCommissionCurrentPeriod = $commissionEarnedCurrentPeriod->sum('commission_amount');


        // Add the total commission to each sales rep object
        $salesRep->total_commission_earned = $totalCommissionBeforePeriod + $totalCommissionCurrentPeriod;

        // Get commission information for the sales representative
        $Commissionpayed = Commission::where('sales_rep_id', $salesRep->user_id)->where('paid', 1)
            ->whereBetween('created_at', [$startDate, $endDate])->get()->sum('commission_amount');

        // Add the commission information to each sales rep object
        $salesRep->total_commission_payed = $Commissionpayed;

        $commissionEarnedInFuture = Commission::where('sales_rep_id', $salesRep->user_id)
            ->groupBy('customer_id')
            ->pluck('customer_id')
            ->toArray();

        // $salesRep->total_commissionEarnedInFuture_customer_id = $commissionEarnedInFuture;


        // Create an instance of the Customer model
        $customerModel = new Customer();

        // Initialize an array to store the detailed information for each customer
        $detailedCustomerInfo = [];

        foreach ($commissionEarnedInFuture as $customerId) {
            // Retrieve customer information for the current customer ID
            $customerInfoCollection = $customerModel->getRecurringPaymentsPendingByCustomerId([$customerId]);

            // Check if the collection is not empty before trying to access its first item
            if (!$customerInfoCollection->isEmpty()) {
                $customerInfo = $customerInfoCollection->first();

                // Build the detailed customer information array and store it in $detailedCustomerInfo
                $detailedCustomerInfo[] = [
                    'customer_id' => $customerId,
                    'referral_username' => $customerInfo->referral_username,
                    'total_recurring_payments_pending' => $customerInfo->total_recurring_payments_pending,
                ];
            }
        }

        // Assign the detailed customer information array to $salesRep->total_commissionEarnedInFuture
        // $salesRep->total_commissionEarnedInFuture = $detailedCustomerInfo;

        $totalRecurringPayments = 0;

        foreach ($detailedCustomerInfo as $customerInfo) {
            $totalRecurringPayments += floatval($customerInfo['total_recurring_payments_pending']);
        }

        // Now, $totalRecurringPayments contains the sum of total_recurring_payments_pending values
        // $salesRep->total_recurring_payments = $totalRecurringPayments;

        // Calculate the commission to be earned based on the formula
        $commissiontobeearnedfuture = $totalRecurringPayments * ($salesRep->commission / 100);

        // Add the calculated commission to the sales rep object
        $salesRep->commission_to_be_earned_future = $commissiontobeearnedfuture;

        return response()->json(['sales_rep' => $salesRep]);
    }

    public function getRecurringPaymentsPendingByCustomer($customerId)
    {
        $customer = new Customer();
        $customerRecurringPayments = $customer->getRecurringPaymentsPendingByCustomerId($customerId);

        return response()->json($customerRecurringPayments, 200);
    }

    public function getCustomSingleSalesRep($user_id)
    {
        $salesRep = DB::table('sale_reps')
            ->join('users', 'sale_reps.user_id', '=', 'users.id')
            ->leftJoin('performance_numbers', 'sale_reps.user_id', '=', 'performance_numbers.sales_rep_id')
            ->select(
                'sale_reps.id',
                'sale_reps.user_id as user_id',
                'users.name as name',
                'users.email as email',
                'users.phone as phone',
                'users.status as status',
                'sale_reps.username',
                'sale_reps.family_name',
                'sale_reps.business_name',
                'sale_reps.address',
                'sale_reps.zip',
                'sale_reps.city',
                'sale_reps.state',
                'sale_reps.paypal_account',
                'sale_reps.photo_path',
                'sale_reps.id_card_front_path',
                'sale_reps.id_card_back_path',
                'sale_reps.form_1099_path',
                'sale_reps.i9_path',
                'sale_reps.w9_path',
                'sale_reps.payment_method',
                'sale_reps.commission as default_commission',
                'sale_reps.created_at',
                'sale_reps.updated_at',
                'performance_numbers.performance_number as performance_number'
            )
            ->where('sale_reps.user_id', $user_id)
            ->first();

        if (!$salesRep) {
            // Handle the case where the sales rep is not found, e.g., return an error response.
            return response()->json(['error' => 'Sales representative not found'], 404);
        }

        // Get commission information for the sales representative
        $commission = Commission::where('sales_rep_id', $salesRep->user_id)->get();

        // Add the commission information to the sales rep object
        $salesRep->commission = $commission;

        // Get Invoice information for the sales rep
        $invoices = InvoiceSr::where('role', 'salesreps')->where('role_user_id', $salesRep->user_id)->get();

        // Add the Invoice information to the sales rep object
        $salesRep->invoices = $invoices;

        // Get Payments information for the sales rep
        $payments = Payout::where('role', 'salesreps')->where('role_user_id', $salesRep->user_id)->get();

        // Add the Invoice information to the sales rep object
        $salesRep->payments = $payments;

        // Retrieve customers associated with the sales rep
        $customers = Customer::where('referral_username', $salesRep->username)
            ->join('users', 'customers.user_id', '=', 'users.id')
            ->get();

        // Attach subscription information to each customer
        $customers->each(function ($customer) {
            $subscription = Subscription::where('customer_id', $customer->user_id)->first();
            $customer->subscription = $subscription;
            $customerPayments = Payment::where('customer_id', $customer->user_id)->get();
            $customer->customer_payment = $customerPayments;

            // Add the package title to the customer object if a subscription exists
            if ($subscription) {
                $customer->package_title = $subscription->package->title;
                $customer->package_term_months = $subscription->package->term_months;
            }
        });

        // Assign the retrieved customers to the sales rep
        $salesRep->customers = $customers;

        return $salesRep;
    }


    public function checkSalesRepsUsername($username)
    {
        $saleReps = SaleRep::where('username', $username)->count();

        if ($saleReps === 0) {
            return response()->json(false);
        } else {
            return response()->json(true);
        }
    }

    public function checkSalesRepsUsernameStatus($username)
    {
        $saleReps = SaleRep::where('username', $username)->first();

        if ($saleReps) {
            $user = User::find($saleReps->user_id);

            if ($user) {
                $salesrepsstatus = $user->status;
                return $salesrepsstatus;
            } else {
                return 'User not found';
            }
        } else {
            return 'SaleRep not found';
        }
    }


    public function checkSalesRepsEmail($email)
    {
        $saleRepsEmail = User::where('email', $email)->count();

        if ($saleRepsEmail === 0) {
            return response()->json(false);
        } else {
            return response()->json(true);
        }
    }



    public function storeSalesReps(Request $request)
    {


        // Validate and save the user data
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'phone' => $request->input('phone'),
            'role' => $request->input('role'),
            'status' => $request->input('status')
        ]);

        // Check if a role name is provided in the request
        if ($request->has('role')) {
            $roleName = $request->input('role');

            // Find the role by name
            $findrole = Role::where('name', $roleName)->first();

            // Check if the role exists
            if ($findrole) {
                // Assign the role to the new user
                $user->assignRole($findrole);
            } else {
                // Handle the case where the specified role does not exist
                // You can return an error response or take appropriate action here
            }
        }
        // Load user's roles and permissions
        $user->load('roles.permissions');

        $salesReps = SaleRep::create([
            'user_id' => $user->id,
            'username' => $request->input('username'),
            'paypal_account' => $request->input('paypal_account'),
            'commission' => $request->input('commission'),
        ]);

        //Load data in Perfomance Table

        $PerformanceNumber = PerformanceNumber::create([
            'sales_rep_id' => $user->id,
            'performance_number' => $request->input('performance_num'),

        ]);


        // Return user details, roles, and permissions in the response
        return response()->json([
            'salesreps' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->roles->pluck('name')->implode(', '),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'username' => $salesReps->username,
                'payment_method' => $salesReps->payment_method,
                'commission' => $salesReps->commission,
                'performance_number' => $PerformanceNumber->performance_number,
            ], 'message' => 'New Sales Representative Created Successfully',
        ], 201);
    }

    public function onlinestoreSalesReps(Request $request)
    {
        // Validate the request data
        // $request->validate([
        //     'email' => 'required|email|unique:users,email',
        //     'password' => 'required|string|min:6',
        //     'username' => 'required|string|unique:sale_reps,username'
        // ]);

        // Validate and save the user data
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'phone' => $request->input('phone'),
            'role' => $request->input('role'),
            'status' => $request->input('status')
        ]);

        // Check if a role name is provided in the request
        if ($request->has('role')) {
            $roleName = $request->input('role');

            // Find the role by name
            $findrole = Role::where('name', $roleName)->first();

            // Check if the role exists
            if ($findrole) {
                // Assign the role to the new user
                $user->assignRole($findrole);
            } else {
                // Handle the case where the specified role does not exist
                // You can return an error response or take appropriate action here
            }
        }
        // Load user's roles and permissions
        $user->load('roles.permissions');
        $defaultComission = Setting::where('key', 'default_commission')->first();
        $salesReps = SaleRep::create([
            'user_id' => $user->id,
            'username' => $request->input('username'),
            'family_name' => $request->input('family_name'),
            'business_name' => $request->input('business_name'),
            'address' => $request->input('address'),
            'zip' => $request->input('zip'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'paypal_account' => $request->input('paypal_account'),
            'payment_method' => null,
            'commission' =>  $defaultComission->value,
            'photo_path' => null,
            'id_card_front_path' => null,
            'id_card_back_path' => null,
            'form_1099_path' => null,
            'i9_path' => null,
            'w9_path' => null,
        ]);

        $setting = Setting::where('key', 'default_performance_number')->first();

        if ($setting) {
            $defaultValue = $setting->value;
        } else {
            // Handle the case where the setting is not found
            $defaultValue = null; // Set a default value or handle accordingly
        }
        //Load data in Perfomance Table

        $PerformanceNumber = PerformanceNumber::create([
            'sales_rep_id' => $user->id,
            'performance_number' => $defaultValue,

        ]);

        // Auto-login the user
        Auth::login($user);
        $token = $user->createToken('auth-token')->plainTextToken;
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'salesreps' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->roles->pluck('name')->implode(', '),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'username' => $salesReps->username,
                'family_name' => $salesReps->family_name,
                'business_name' => $salesReps->business_name,
                'address' => $salesReps->address,
                'zip' => $salesReps->zip,
                'city' => $salesReps->city,
                'state' => $salesReps->state,
                'paypal_account' => $salesReps->paypal_account,
                'payment_method' => $salesReps->payment_method,
                'commission' => $salesReps->commission,
                'photo_path' => $salesReps->photo_path,
                'id_card_front_path' => $salesReps->id_card_front_path,
                'id_card_back_path' => $salesReps->id_card_back_path,
                'form_1099_path' => $salesReps->form_1099_path,
                'i9_path'   => $salesReps->i9_path,
                'w9_path'   => $salesReps->w9_path,
                'performance_number' => $defaultValue,
            ],
            'message' => 'Thanks for Successfully Created',
        ], 201);
    }

    public function updateOnlineStoreSalesReps(Request $request, $id)
    {
        // Validate the request data as needed

        // Find the user, sales rep, and performance number by ID
        $user = User::find($id);
        $salesRep = SaleRep::where('user_id', $id)->first();
        $performanceNumber = PerformanceNumber::where('sales_rep_id', $id)->first();

        // Check if the user, sales rep, and performance number exist
        if (!$user || !$salesRep || !$performanceNumber) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        // Update user data
        $userData = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'phone' => $request->input('phone'),
            'role' => $request->input('role'),
            'status' => $request->input('status'),
        ];

        // Remove null or empty values from the data array
        $userData = array_filter($userData, function ($value) {
            return $value !== null && $value !== '';
        });

        // Check if there are fields to update
        if (!empty($userData)) {
            $user->update($userData);
        }

        // Update sales rep data
        $salesRepData = [
            'username' => $request->input('username'),
            'family_name' => $request->input('family_name'),
            'business_name' => $request->input('business_name'),
            'address' => $request->input('address'),
            'zip' => $request->input('zip'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'paypal_account' => $request->input('paypal_account'),
            // Add other fields as needed
        ];

        // Remove null or empty values from the data array
        $salesRepData = array_filter($salesRepData, function ($value) {
            return $value !== null && $value !== '';
        });

        // Check if there are fields to update
        if (!empty($salesRepData)) {
            $salesRep->update($salesRepData);
        }

        // Update performance number data
        $performanceNumberData = [
            'performance_number' => $request->input('performance_num'),
        ];

        // Remove null or empty values from the data array
        $performanceNumberData = array_filter($performanceNumberData, function ($value) {
            return $value !== null && $value !== '';
        });

        // Check if there are fields to update
        if (!empty($performanceNumberData)) {
            $performanceNumber->update($performanceNumberData);
        }

        // Return the response
        return response()->json([
            'salesreps' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->roles->pluck('name')->implode(', '),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'username' => $salesRep->username,
                'family_name' => $salesRep->family_name,
                'business_name' => $salesRep->business_name,
                'address' => $salesRep->address,
                'zip' => $salesRep->zip,
                'city' => $salesRep->city,
                'state' => $salesRep->state,
                'mobile' => $salesRep->mobile,
                'paypal_account' => $salesRep->paypal_account,
                'payment_method' => $salesRep->payment_method,
                'commission' => $salesRep->commission,
                'photo_path' => $salesRep->photo_path,
                'id_card_front_path' => $salesRep->id_card_front_path,
                'id_card_back_path' => $salesRep->id_card_back_path,
                'form_1099_path' => $salesRep->form_1099_path,
                'i9_path'   => $salesRep->i9_path,
                'w9_path'   => $salesRep->w9_path,
                'performance_number' => $performanceNumber->performance_number,
            ],
            'message' => 'Successfully updated',
        ], 200);
    }


    public function updateSalesReps(Request $request, $user_id)
    {
        // Find the user associated with the given $user_id
        $user = User::find($user_id);

        // Check if the user exists
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Update the user data (excluding password if it's null)
        $userData = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'status' => $request->input('status'),
        ];

        // Only update the password if it's provided
        if ($request->input('password')) {
            $userData['password'] = $request->input('password');
        }

        $user->update($userData);

        // Update the associated SaleRep record
        $saleRep = SaleRep::where('user_id', $user_id)->first();

        if (!$saleRep) {
            return response()->json(['message' => 'SaleRep not found'], 404);
        }

        $saleRep->update([
            'username' => $request->input('username'),
            'paypal_account' => $request->input('paypal_account'),
            'commission' => $request->input('commission'),
        ]);

        // Update the associated PerformanceNumber record
        $performanceNumber = PerformanceNumber::where('sales_rep_id', $user_id)->first();

        if (!$performanceNumber) {
            return response()->json(['message' => 'PerformanceNumber not found'], 404);
        }

        $performanceNumber->update([
            'performance_number' => $request->input('performance_num'),
        ]);

        return response()->json(['message' => 'Sales Representative and related records updated successfully']);
    }

    public function destroySingleSalesReps($user_id)
    {
        $saleRep = SaleRep::where('user_id', $user_id)->first();
        $saleRep->delete();
        return response()->json(['message' => 'Record deleted'], 200);
    }


    //Calculation & Logic

    public function editPerformanceNumber(Request $request, $salesRepId)
    {
        // Validation and authorization checks if needed

        // Find the specific PerformanceNumber record based on sales_rep_id
        $performanceNumberData = PerformanceNumber::where('sales_rep_id', $salesRepId)->firstOrFail();

        // Update the performance number for the sales rep
        $performanceNumberData->update([
            'performance_number' => $request->input('performance_number'),
        ]);

        return response()->json(['message' => 'Performance number updated']);
    }

    public function editSalesRepsComission(Request $request, $salesRepId)
    {
        // Validation and authorization checks if needed

        // Find the specific PerformanceNumber record based on sales_rep_id
        $SalesReps = SaleRep::where('user_id', $salesRepId)->firstOrFail();

        // Update the performance number for the sales rep
        $SalesReps->update([
            'commission' => $request->input('commission'),
        ]);

        return response()->json(['message' => 'Commission updated']);
    }


    public function checkPaymentStatus($salesRepId, $month)
    {
        // Fetch the sales rep
        $salesRep = SaleRep::findOrFail($salesRepId);

        // Check if the sales rep's commissions for the given month are paid
        $commission = Commission::where('sales_rep_id', $salesRepId)
            ->where('month', $month)
            ->first();

        if ($commission && $commission->paid) {
            return response()->json(['message' => 'Commissions are paid'], 200);
        } else {
            return response()->json(['message' => 'Commissions are not paid'], 200);
        }
    }

    public function getAllsalesRepsInvoiceList()
    {
        // Fetch the sales rep Invoice
        $invoices = Invoice::get();

        return response()->json(['invoices' => $invoices]);
    }

    public function getAllsalesRepsCommissionList()
    {
        // Fetch the sales rep Invoice
        $Commissions = Commission::get();

        return response()->json(['Commissions' => $Commissions]);
    }


    public function getSingleSalesRepInvoice($id)
    {
        // Fetch the unpaid sales rep Invoice by ID
        $invoice = Invoice::where('id', $id)
            ->where('status', 'unpaid')
            ->first();

        if ($invoice) {
            return response()->json(['invoice' => $invoice]);
        } else {
            // Handle the case where no matching unpaid invoice is found
            return response()->json(['message' => 'Unpaid invoice not found.'], 404);
        }
    }


    //     Stats related to: 
    // Sale Rep income per period
    // Sale Rep income already invoiced and payment received
    // Sale reps income and not yet invoiced
    // Sale reps income invoiced and not yet paid
    // Number of new customers per day, period, etc
    // Forecast revenue based on registered paying customers
    // Proﬁle

    public function getStats()
    {
        $saleRepIncome = $this->getSaleRepIncome();
        $invoicedAndPaid = $this->getInvoicedAndPaid();
        $incomeNotInvoiced = $this->getIncomeNotInvoiced();
        $invoicedNotPaid = $this->getInvoicedNotPaid();
        $newCustomersPerDay = $this->getNewCustomersPerDay();
        $forecastRevenue = $this->getForecastRevenue();
        $mtdnewcustomersearnings = $this->getMTDnewCustomersEarnings();
        $oldcustomersearnings = $this->getOLDCustomersEarnings();
        $totalFutureEarnings = $this->totalFutureEarnes();

        return response()->json([
            'sale_rep_income_current_month' => $saleRepIncome,
            'sale_rep_invoiced_and_paid' => $invoicedAndPaid,
            'sale_rep_income_not_invoiced' => $incomeNotInvoiced,
            'sale_rep_invoiced_not_paid' => $invoicedNotPaid,
            'sale_rep_new_customers_per_day' => $newCustomersPerDay,
            'sale_rep_forecast_revenue' => $forecastRevenue,
            'mtdnewcustomersearnings' => $mtdnewcustomersearnings,
            'oldcustomersearnings' => $oldcustomersearnings,
            'totalcustomersearnings' => $mtdnewcustomersearnings + $oldcustomersearnings,
            'totalFutureEarnings' =>  $totalFutureEarnings,
        ]);
    }

    // Implement methods for each specific statistic

    public function getSaleRepIncome()
    {
        // Get the authenticated user
        $user = auth()->user();

        // Get the current month
        $currentMonth = Carbon::now()->month;

        // Fetch the total sum of commissions for the current month for the authenticated user
        $totalCommission = Commission::where('sales_rep_id', $user->id)
            ->whereMonth('created_at', '=', $currentMonth)
            ->sum('commission_amount');

        return $totalCommission;
    }

    public function getInvoicedAndPaid()
    {
        // Get the authenticated user
        $user = auth()->user();

        // Fetch income from commissions where paid is true for the authenticated user
        $invoicedAndPaid = Commission::where('sales_rep_id', $user->id)
            ->where('paid', true)
            ->sum('commission_amount');

        return $invoicedAndPaid;
    }

    public function getIncomeNotInvoiced()
    {
        // Get the authenticated user
        $user = auth()->user();

        // Fetch income from commissions where paid is false for the authenticated user
        $incomeNotInvoiced = Commission::where('sales_rep_id', $user->id)
            ->where('paid', false)
            ->sum('commission_amount');

        return $incomeNotInvoiced;
    }

    public function getInvoicedNotPaid()
    {
        // Get the authenticated user
        $user = auth()->user();

        // Fetch income from commissions where paid is false for the authenticated user
        $InvoicedNotPaid = Commission::where('sales_rep_id', $user->id)
            ->where('paid', false)
            ->sum('commission_amount');

        return $InvoicedNotPaid;
    }


    public function getNewCustomersPerDay()
    {
        // Get the authenticated user
        $user = Auth::user();

        // Check if $user->saleRep is set, if not, set it to null
        $saleRepUsername = $user->saleRep ? $user->saleRep->username : null;

        // Get the current date in UTC
        $today = Carbon::now('UTC')->toDateString();

        // Fetch the total number of customers for today for the authenticated user's sales representative
        $totalCustomersTodayCount = Customer::where('referral_username', $saleRepUsername)
            ->whereDate('created_at', $today)
            ->count();

        // Create an array with date and username
        $result = [
            'date' => $today,
            'username' => $saleRepUsername,
            'totalCustomers' => $totalCustomersTodayCount,
        ];

        return $totalCustomersTodayCount;
    }

    public function getForecastRevenue()
    {
        // Logic to calculate forecast revenue based on registered paying customers
        return null;
    }

    public function getMTDnewCustomersEarnings()
    {
        // Get the authenticated user
        $user = Auth::user();

        // Check if $user->saleRep is set, if not, set it to null
        $saleRepUsername = $user->saleRep ? $user->saleRep->username : null;

        $formattedDate = Carbon::now()->format('Y-m');

        $totalNewCustomers = Customer::where('referral_username', $saleRepUsername)
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$formattedDate])
            ->pluck('user_id')
            ->toArray();

        // Initialize $commissionEarned to 0 before the loop
        $commissionEarned = 0;

        // Collect all customer IDs before querying the Commission model
        $customerIds = [];

        foreach ($totalNewCustomers as $customerId) {
            $customerIds[] = $customerId;
        }

        // Check if there are any customer IDs before querying the Commission model
        if (!empty($customerIds)) {
            // Sum the commission for all customer IDs
            $commissionEarned = Commission::whereIn('customer_id', $customerIds)
                ->where("commission_type", "1st Invoice")
                ->sum('commission_amount');
        }

        return $commissionEarned;
    }

    public function getOLDCustomersEarnings()
    {
        // Get the authenticated user
        $user = Auth::user();

        // Check if $user->saleRep is set, if not, set it to null
        $saleRepUsername = $user->saleRep ? $user->saleRep->username : null;

        $formattedDate = Carbon::now()->format('Y-m');

        $totalNewCustomers = Customer::where('referral_username', $saleRepUsername)
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') <= ?", [$formattedDate])
            ->pluck('user_id')
            ->toArray();

        // Initialize $commissionEarned to 0 before the loop
        $commissionEarned = 0;

        // Collect all customer IDs before querying the Commission model
        $customerIds = [];

        foreach ($totalNewCustomers as $customerId) {
            $customerIds[] = $customerId;
        }

        // Check if there are any customer IDs before querying the Commission model
        if (!empty($customerIds)) {
            // Sum the commission for all customer IDs
            $commissionEarned = Commission::whereIn('customer_id', $customerIds)
                ->where("commission_type", "recurring")
                ->sum('commission_amount');
        }

        return $commissionEarned;
    }


    public function totalFutureEarnes()
    {
        // Get the authenticated user
        $user = Auth::user();

        $commissionEarnedInFuture = Commission::where('sales_rep_id', $user->id)
            ->groupBy('customer_id')
            ->pluck('customer_id')
            ->toArray();

        // Create an instance of the Customer model
        $customerModel = new Customer();

        // Initialize an array to store the detailed information for each customer
        $detailedCustomerInfo = [];

        foreach ($commissionEarnedInFuture as $customerId) {
            // Retrieve customer information for the current customer ID
            $customerInfoCollection = $customerModel->getRecurringPaymentsPendingByCustomerId([$customerId]);
            error_log($customerInfoCollection);
            // Check if the collection is not empty before trying to access its first item
            if (!$customerInfoCollection->isEmpty()) {
                $customerInfo = $customerInfoCollection->first();

                // Build the detailed customer information array and store it in $detailedCustomerInfo
                $detailedCustomerInfo[] = [
                    'customer_id' => $customerId,
                    'referral_username' => $customerInfo->referral_username,
                    'total_recurring_payments_pending' => $customerInfo->total_recurring_payments_pending,
                ];
            }
        }

        // Assign the detailed customer information array to $salesRep->total_commissionEarnedInFuture
        // $salesRep->total_commissionEarnedInFuture = $detailedCustomerInfo;

        $totalRecurringPayments = 0;

        foreach ($detailedCustomerInfo as $customerInfo) {
            $totalRecurringPayments += floatval($customerInfo['total_recurring_payments_pending']);
        }

        // Now, $totalRecurringPayments contains the sum of total_recurring_payments_pending values
        // $salesRep->total_recurring_payments = $totalRecurringPayments;

        // Calculate the commission to be earned based on the formula
        $commissiontobeearnedfuture = $totalRecurringPayments * ($user->saleRep->commission / 100);

        // Add the calculated commission to the sales rep object
        return $commissiontobeearnedfuture;
    }



    public function getAdminStats($userId)
    {
        $mtdnewcustomersearnings = $this->getMTDnewCustomersEarningsFromAdmin($userId);
        $oldcustomersearnings = $this->getOLDCustomersEarningsFromAdmin($userId);
        $totalFutureEarnings = $this->totalFutureEarnesFromAdmin($userId);

        $totalcustomersearnings = $mtdnewcustomersearnings + $oldcustomersearnings;

        return response()->json([
            'mtdnewcustomersearnings' => $mtdnewcustomersearnings,
            'oldcustomersearnings' => $oldcustomersearnings,
            'totalcustomersearnings' => $totalcustomersearnings,
            'totalFutureEarnings' => $totalFutureEarnings,
        ]);
    }

    public function getMTDnewCustomersEarningsFromAdmin($userId)
    {
        // Get the authenticated user
        $user = User::find($userId);

        // Check if $user->saleRep is set, if not, set it to null
        $saleRepUsername = $user->saleRep ? $user->saleRep->username : null;
        // error_log("username : $saleRepUsername");
        $formattedDate = Carbon::now()->format('Y-m');
        // error_log("Formatted Date: $formattedDate");
        $totalNewCustomers = Customer::where('referral_username', $saleRepUsername)
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$formattedDate])
            ->pluck('user_id')
            ->toArray();
        // error_log("New Total New Customers: " . print_r($totalNewCustomers, true));
        // Initialize $commissionEarned to 0 before the loop
        $commissionEarned = 0;

        // Collect all customer IDs before querying the Commission model
        $customerIds = [];

        foreach ($totalNewCustomers as $customerId) {
            $customerIds[] = $customerId;
        }

        // Check if there are any customer IDs before querying the Commission model
        if (!empty($customerIds)) {
            // Sum the commission for all customer IDs
            $commissionEarned = Commission::whereIn('customer_id', $customerIds)
                ->where("commission_type", "1st Invoice")
                ->sum('commission_amount');
        }

        return $commissionEarned;
    }

    public function getOLDCustomersEarningsFromAdmin($userId)
    {
        // Get the authenticated user
        $user = User::find($userId);

        // Check if $user->saleRep is set, if not, set it to null
        $saleRepUsername = $user->saleRep ? $user->saleRep->username : null;
        // error_log("username : $saleRepUsername");
        $formattedDate = Carbon::now()->format('Y-m');
        // error_log("Old Formatted Date: $formattedDate");
        $totalNewCustomers = Customer::where('referral_username', $saleRepUsername)
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') <= ?", [$formattedDate])
            ->pluck('user_id')
            ->toArray();

        // error_log("old Total New Customers: " . print_r($totalNewCustomers, true));
        // Initialize $commissionEarned to 0 before the loop
        $commissionEarned = 0;

        // Collect all customer IDs before querying the Commission model
        $customerIds = [];

        foreach ($totalNewCustomers as $customerId) {
            $customerIds[] = $customerId;
        }

        // Check if there are any customer IDs before querying the Commission model
        if (!empty($customerIds)) {
            // Sum the commission for all customer IDs
            $commissionEarned = Commission::whereIn('customer_id', $customerIds)
                ->where("commission_type", "recurring")
                ->sum('commission_amount');
        }

        return $commissionEarned;
    }


    public function totalFutureEarnesFromAdmin($userId)
    {
        // Get the authenticated user
        $user = User::find($userId);

        $commissionEarnedInFuture = Commission::where('sales_rep_id', $user->id)
            ->groupBy('customer_id')
            ->pluck('customer_id')
            ->toArray();

        // Create an instance of the Customer model
        $customerModel = new Customer();

        // Initialize an array to store the detailed information for each customer
        $detailedCustomerInfo = [];

        foreach ($commissionEarnedInFuture as $customerId) {
            // Retrieve customer information for the current customer ID
            $customerInfoCollection = $customerModel->getRecurringPaymentsPendingByCustomerId([$customerId]);

            // Check if the collection is not empty before trying to access its first item
            if (!$customerInfoCollection->isEmpty()) {
                $customerInfo = $customerInfoCollection->first();

                // Build the detailed customer information array and store it in $detailedCustomerInfo
                $detailedCustomerInfo[] = [
                    'customer_id' => $customerId,
                    'referral_username' => $customerInfo->referral_username,
                    'total_recurring_payments_pending' => $customerInfo->total_recurring_payments_pending,
                ];
            }
        }

        // Assign the detailed customer information array to $salesRep->total_commissionEarnedInFuture
        // $salesRep->total_commissionEarnedInFuture = $detailedCustomerInfo;

        $totalRecurringPayments = 0;

        foreach ($detailedCustomerInfo as $customerInfo) {
            $totalRecurringPayments += floatval($customerInfo['total_recurring_payments_pending']);
        }

        // Now, $totalRecurringPayments contains the sum of total_recurring_payments_pending values
        // $salesRep->total_recurring_payments = $totalRecurringPayments;

        // Calculate the commission to be earned based on the formula
        $commissiontobeearnedfuture = $totalRecurringPayments * ($user->saleRep->commission / 100);;

        // Add the calculated commission to the sales rep object
        return $commissiontobeearnedfuture;
    }


    public function twoMonthsAgoEarningsAndCustomersCount()
    {
        // Get the authenticated user
        $user = Auth::user();
        $twoMonthsAgo = Carbon::now()->subMonths(2)->firstOfMonth()->format('Y-m');

        $commissionEarnedInFuture = Commission::where('sales_rep_id', $user->id)
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') < ?", [$twoMonthsAgo])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(commission_amount) as total_commission')
            ->pluck('total_commission', 'customer_id')
            ->toArray();

        // Calculate the customers count and total earnings
        $customersCount = count($commissionEarnedInFuture);
        $earningsTotal = array_sum($commissionEarnedInFuture);

        // Return the response in JSON format
        return response()->json(['customers' => $customersCount, 'earningsTotal' => $earningsTotal]);
    }

    public function lastMonthsEarningsAndCustomersCount()
    {
        // Get the authenticated user
        $user = Auth::user();
        $lastMonthsAgo = Carbon::now()->subMonths(1)->firstOfMonth()->format('Y-m');
        // error_log("Formatted Date: $lastMonthsAgo");
        $commissionEarnedInFuture = Commission::where('sales_rep_id', $user->id)
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$lastMonthsAgo])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(commission_amount) as total_commission')
            ->pluck('total_commission', 'customer_id')
            ->toArray();

        // Calculate the customers count and total earnings
        $customersCount = count($commissionEarnedInFuture);
        $earningsTotal = array_sum($commissionEarnedInFuture);

        // Return the response in JSON format
        return response()->json(['customers' => $customersCount, 'earningsTotal' => $earningsTotal]);
    }

    public function thisMonthsEarningsAndCustomersCount()
    {
        // Get the authenticated user
        $user = Auth::user();
        $thisMonth = Carbon::now()->format('Y-m');

        $commissionEarnedInFuture = Commission::where('sales_rep_id', $user->id)
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$thisMonth])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(commission_amount) as total_commission')
            ->pluck('total_commission', 'customer_id')
            ->toArray();

        // Calculate the customers count and total earnings
        $customersCount = count($commissionEarnedInFuture);
        $earningsTotal = array_sum($commissionEarnedInFuture);

        // Return the response in JSON format
        return response()->json(['customers' => $customersCount, 'earningsTotal' => $earningsTotal]);
    }


    public function futureEarningsAndCustomersCount()
    {
        // Get the authenticated user
        $user = Auth::user();
        // Check if $user->saleRep is set, if not, set it to null
        $saleRepcommission = $user->saleRep ? $user->saleRep->commission : null;

        // Retrieve customer IDs with future commissions
        $commissionEarnedInFuture = Commission::where('sales_rep_id', $user->id)
            ->groupBy('customer_id')
            ->pluck('customer_id')
            ->toArray();

        // Create an instance of the Customer model
        $customerModel = new Customer();

        // Initialize an array to store the detailed information for each customer
        $detailedCustomerInfo = [];
        $totalCustomersCount = 0;

        foreach ($commissionEarnedInFuture as $customerId) {
            // Retrieve customer information for the current customer ID
            $customerInfoCollection = $customerModel->getRecurringPaymentsPendingByCustomerId([$customerId]);

            // Check if the collection is not empty before trying to access its first item
            if (!$customerInfoCollection->isEmpty()) {
                $customerInfo = $customerInfoCollection->first();

                // Increment the total customers count
                $totalCustomersCount++;

                // Build the detailed customer information array and store it in $detailedCustomerInfo
                $detailedCustomerInfo[] = [
                    'customer_id' => $customerId,
                    'referral_username' => $customerInfo->referral_username,
                    'total_recurring_payments_pending' => $customerInfo->total_recurring_payments_pending,
                ];
            }
        }

        // Calculate the total recurring payments and commission to be earned
        $totalRecurringPayments = 0;

        foreach ($detailedCustomerInfo as $customerInfo) {
            $totalRecurringPayments += floatval($customerInfo['total_recurring_payments_pending']);
        }

        // Calculate the commission to be earned based on the formula
        $commissionToBeEarnedFuture = $totalRecurringPayments * ($saleRepcommission / 100);

        // Create an array to return both the commission and total customers count
        $result = [
            'total_furute_earnings' => $commissionToBeEarnedFuture,
            'total_customers_count' => $totalCustomersCount,
        ];

        return $result;
    }



    public function updateFilesForSalesReps(Request $request, $id)
    {
        // Find sales rep by ID
        $salesRep = SaleRep::where('user_id', $id)->first();

        // Check if the user, sales rep, and performance number exist
        if (!$salesRep) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        // Update sales rep data
        $salesRepData = [
            'photo_path' => $request->input('photo_path'),
            'id_card_front_path' => $request->input('id_card_front_path'),
            'id_card_back_path' => $request->input('id_card_back_path'),
            'form_1099_path' => $request->input('form_1099_path'),
            'i9_path' => $request->input('i9_path'),
            'w9_path' => $request->input('w9_path'),
            // Add other fields as needed
        ];

        // Attempt to update the sales rep data
        $updated = $salesRep->update($salesRepData);

        // Check if the update was successful
        if ($updated) {
            // Return success response
            return response()->json(['message' => 'Successfully Updated'], 200);
        } else {
            // Return error response
            return response()->json(['message' => 'Failed to update'], 500);
        }
    }

    public function getEarningsAllSalesRep(Request $request)
    {
        $perPage = $request->input('perPage', 500);
        $startDate = $request->input('startDate', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay()->toDateString();


        $sale_reps = DB::table('sale_reps')
            ->join('users', 'sale_reps.user_id', '=', 'users.id')
            ->select(
                'sale_reps.id',
                'sale_reps.user_id as user_id',
                'users.name as name',
                'users.email as email',
                'users.phone as phone',
                'users.status as status',
                'sale_reps.username',
                'sale_reps.family_name',
                'sale_reps.business_name',
                'sale_reps.address',
                'sale_reps.zip',
                'sale_reps.city',
                'sale_reps.state',
                'sale_reps.commission',
                'sale_reps.created_at',
                'sale_reps.updated_at',
            )
            // ->whereBetween('sale_reps.created_at', [$startDate, $endDate])
            ->orderBy('sale_reps.created_at', 'desc')
            ->paginate($perPage);

        // Loop through each sales rep and get additional data
        foreach ($sale_reps as $salesRep) {

            // Get Total Earnings information for the sales rep
            $total_earnings = Commission::where('sales_rep_id', $salesRep->user_id)->get()->sum('commission_amount');

            // Add the Total Earnings information to each sales rep object
            $salesRep->total_earnings = $total_earnings;


            // Get Earning for the period selected information for the sales rep
            $earnings_for_the_period = Commission::where('sales_rep_id', $salesRep->user_id)
                ->whereBetween('created_at', [$startDate, $endDate])->get()->sum('commission_amount');

            // Add the Total Earnings information to each sales rep object
            $salesRep->earnings_for_the_period = $earnings_for_the_period;




            $commissionEarnedInFuture = Commission::where('sales_rep_id', $salesRep->user_id)
                ->groupBy('customer_id')
                ->pluck('customer_id')
                ->toArray();

            // $salesRep->total_commissionEarnedInFuture_customer_id = $commissionEarnedInFuture;


            // Create an instance of the Customer model
            $customerModel = new Customer();

            // Initialize an array to store the detailed information for each customer
            $detailedCustomerInfo = [];

            foreach ($commissionEarnedInFuture as $customerId) {
                // Retrieve customer information for the current customer ID
                $customerInfoCollection = $customerModel->getRecurringPaymentsPendingByCustomerId([$customerId]);

                // Check if the collection is not empty before trying to access its first item
                if (!$customerInfoCollection->isEmpty()) {
                    $customerInfo = $customerInfoCollection->first();

                    // Build the detailed customer information array and store it in $detailedCustomerInfo
                    $detailedCustomerInfo[] = [
                        'customer_id' => $customerId,
                        'referral_username' => $customerInfo->referral_username,
                        'total_recurring_payments_pending' => $customerInfo->total_recurring_payments_pending,
                    ];
                }
            }

            // Assign the detailed customer information array to $salesRep->total_commissionEarnedInFuture
            // $salesRep->total_commissionEarnedInFuture = $detailedCustomerInfo;

            $totalRecurringPayments = 0;

            foreach ($detailedCustomerInfo as $customerInfo) {
                $totalRecurringPayments += floatval($customerInfo['total_recurring_payments_pending']);
            }

            // Now, $totalRecurringPayments contains the sum of total_recurring_payments_pending values
            // $salesRep->total_recurring_payments = $totalRecurringPayments;

            // Calculate the commission to be earned based on the formula
            $commissiontobeearnedfuture = $totalRecurringPayments * ($salesRep->commission / 100);

            // Add the calculated commission to the sales rep object
            $salesRep->commission_to_be_earned_future = $commissiontobeearnedfuture;


            // Get Payments information for the sales rep
            $earnings_already_paid = Payout::where('role', 'salesreps')->where('role_user_id', $salesRep->user_id)->get()->sum('amount');

            // Add the Invoice information to the sales rep object
            $salesRep->earnings_already_paid = $earnings_already_paid;
        }

        if ($sale_reps) {
            return $sale_reps;
        } else {
            return response()->json(['message' => 'Record not found'], 404);
        }
    }

    public function getEarningsAllSalesReptest(Request $request)
    {
        $perPage = $request->input('perPage', 500);
        $startDate = $request->input('startDate', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay()->toDateString();

        $sale_reps = DB::table('sale_reps')
            ->join('users', 'sale_reps.user_id', '=', 'users.id')

            ->select(
                'sale_reps.id',
                'sale_reps.user_id as user_id',
                'users.name as name',
                'users.email as email',
                'users.phone as phone',
                'users.status as status',
                'sale_reps.username',
                'sale_reps.family_name',
                'sale_reps.business_name',
                'sale_reps.address',
                'sale_reps.zip',
                'sale_reps.city',
                'sale_reps.state',
                'sale_reps.commission',
                'sale_reps.created_at',
                'sale_reps.updated_at',
            )
            ->orderBy('sale_reps.created_at', 'desc')
            ->paginate($perPage);

        // Loop through each sales rep and get additional data
        foreach ($sale_reps as $salesRep) {
            // Get Total Earnings information for the sales rep for the specified date range
            $total_earnings = Commission::where('sales_rep_id', $salesRep->user_id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('commission_amount');
            $salesRep->total_earnings = $total_earnings;

            // Get Earnings for the period selected information for the sales rep
            $earnings_for_the_period = Commission::where('sales_rep_id', $salesRep->user_id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('commission_amount');
            $salesRep->earnings_for_the_period = $earnings_for_the_period;

            // Get future commission to be earned
            $commission_to_be_earned_future = $total_earnings - $earnings_for_the_period;
            $salesRep->commission_to_be_earned_future = $commission_to_be_earned_future;

            // Get Payments information for the sales rep
            $earnings_already_paid = Payout::where('role', 'salesreps')
                ->where('role_user_id', $salesRep->user_id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount');
            $salesRep->earnings_already_paid = $earnings_already_paid;
        }

        return $sale_reps;
    }
}
