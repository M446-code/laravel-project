<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\User;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SaleRep;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class AdminController extends Controller
{
    public function getAllUsers()
    {
        $users = User::with('roles')->get();
        
        $formattedUsers = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'phone' => $user->phone,
                'role_id' => $user->role_id,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'roles' => $user->roles->pluck('name')->implode(', '),
            ];
        });
        
        return response()->json($formattedUsers);
    }

    public function getCustomAllUsers(Request $request)
{
    $perPage = $request->input('perPage', 25);
    $startDate = $request->input('startDate', null);
    $endDate = $request->input('endDate', null);

    $usersQuery = User::with('roles');

    // Apply date range filtering if provided
    if ($startDate && $endDate) {
        $usersQuery->whereBetween('created_at', [$startDate, $endDate]);
    }

    $users = $usersQuery->orderBy('created_at', 'desc')->paginate($perPage);

    $formattedUsers = $users->map(function ($user) {
        // Additional relationships
        $customer = Customer::where('user_id', $user->id)->first();
        $saleRep = SaleRep::where('user_id', $user->id)->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'phone' => $user->phone,
            'role_id' => $user->role_id,
            'status' => $user->status,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'roles' => $user->roles->pluck('name')->implode(', '),
            'customer' => $customer, // Add customer data
            'sale_rep' => $saleRep, // Add saleRep data
        ];
    });

    return response()->json($formattedUsers);
}


    
        public function getSingleUser($id)
        {
             $user = User::with('roles')->find($id);
             return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'phone' => $user->phone,
                'role_id' => $user->role_id,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'roles' => $user->roles->pluck('name')->implode(', '),
            ];
        }
        
        public function storeUser(Request $request)
        {
            // Validate and save the user data
            $user = User::create($request->all());

            // Check if a role name is provided in the request
            if ($request->has('role')) {
                $roleName = $request->input('role');

                // Find the role by name
                $role = Role::where('name', $roleName)->first();

                // Check if the role exists
                if ($role) {
                    // Assign the role to the new user
                    $user->assignRole($role);
                } else {
                    // Handle the case where the specified role does not exist
                    // You can return an error response or take appropriate action here
                }
            }

             // Load user's roles and permissions
        $user->load('roles.permissions');

        // Return user details, roles, and permissions in the response
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->roles->pluck('name')->implode(', '),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],'message' => 'New User Created Successfully',
        ], 201);
        }

        public function updateSingleUser(Request $request, $id)
        {
            $user = User::find($id);

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            // Partial update: Update the user's attributes based on the request data
            $user->update($request->all());

            // Update the user's roles if provided in the request
            if ($request->has('roles')) {
                $roleNames = $request->input('roles');
                $roles = Role::whereIn('name', $roleNames)->get();

                if ($roles) {
                    $user->syncRoles($roles);
                }
            }

            // Load user's roles and permissions
            $user->load('roles.permissions');

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'roles' => $user->roles->pluck('name')->implode(', '),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'message' => 'User Updated',
            ], 200);
        }




        public function destroySingleUser($id)
        {
            $user = User::find($id);

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $user->delete();
            return response()->json(['message' => 'User deleted'], 204);
        }


        
        public function getCustomReportData(Request $request)
        {
            $category = $request->input('category', 'customers'); // Default to 'customers' if no category is provided
            $perPage = $request->input('perPage', 25);
            $startDate = $request->input('startDate', Carbon::now()->subDays(7)->toDateString());
            $endDate = $request->input('endDate', Carbon::now()->toDateString());

            // Add 1 day to the endDate
            $endDate = Carbon::parse($endDate)->addDay()->toDateString();

            $query = DB::table($category)->whereBetween("$category.created_at", [$startDate, $endDate]);

            switch ($category) {
                case 'customers':
                    $query->join('users', "{$category}.user_id", '=', 'users.id')
                        ->select(
                            "{$category}.id",
                            "{$category}.user_id",
                            "{$category}.client_id",
                            "{$category}.business_name",
                            "{$category}.street",
                            "{$category}.zipCode",
                            "{$category}.country",
                            "{$category}.state",
                            "{$category}.city",
                            "{$category}.referral_username",
                            "{$category}.payment_method",
                            "{$category}.created_at",
                            "{$category}.updated_at",
                            'users.name as name',
                            'users.email as email',
                            'users.phone as phone',
                            'users.status as status'
                        );
                    break;

                case 'sale_reps':
                    $query->join('users', "{$category}.user_id", '=', 'users.id')
                        ->select(
                            "{$category}.id",
                            "{$category}.user_id",
                            "{$category}.username",
                            "{$category}.created_at",
                            "{$category}.updated_at",
                            'users.name as name',
                            'users.email as email',
                            'users.phone as phone',
                            'users.status as status'
                        );
                    break;

                // Add cases for other categories

                default:
                    return response()->json(['error' => 'Invalid category specified'], 400);
            }

            $results = $query->orderBy("$category.created_at", 'desc')->paginate($perPage);

            return response()->json($results);
        }


        // Admin Dashboard  All widgets
        public function adminAllWidget()
        {
            //From Customer Invoice tabels
            // Calculate daily revenue from new sales
            $currentDate = now()->format('Y-m-d');
            $dailyRevenueNewSales = Invoice::whereDate('created_at', '=', $currentDate)
                                        ->where('status', '=', 'paid')
                                        ->where('invoice_type', '=', '1st Invoice')
                                        ->sum('total_amount');

            // Calculate daily revenue from recurring sales
            $dailyRevenueRecurringSales = Invoice::whereDate('created_at', '=', $currentDate)
                                                ->where('status', '=', 'paid')
                                                ->where('invoice_type', '=', 'recurring')
                                                ->sum('total_amount');

            // Calculate month-to-date revenue from new sales
            $startOfMonth = now()->startOfMonth()->format('Y-m-d H:i:s');
            $mcurrentDate = now()->format('Y-m-d H:i:s');
            
            // Calculate month-to-date revenue from new sales
            $monthlyRevenueNewSales = Invoice::where('created_at', '>=', $startOfMonth)
            ->where('created_at', '<=', $mcurrentDate)
            ->where('status', '=', 'paid')
            ->where('invoice_type', '=', '1st Invoice')
            ->sum('total_amount');

            // Calculate month-to-date revenue from recurring sales
            $monthlyRevenueRecurringSales = Invoice::where('created_at', '>=', $startOfMonth)
            ->where('created_at', '<=', $mcurrentDate)
            ->where('status', '=', 'paid')
            ->where('invoice_type', '=', 'recurring')
            ->sum('total_amount');

            // Calculate month-to-date total revenue
            $monthlyTotalRevenue = $monthlyRevenueNewSales + $monthlyRevenueRecurringSales;




            // Calculate daily customers from new sales
            $currentDate = now()->format('Y-m-d');
            $dailyCustomersNewSales = Invoice::whereDate('created_at', '=', $currentDate)
                                            ->where('status', '=', 'paid')
                                            ->where('invoice_type', '=', '1st Invoice')
                                            ->distinct('role_user_id')
                                            ->count('role_user_id');

            // Calculate daily customers from recurring sales
            $dailyCustomersRecurringSales = Invoice::whereDate('created_at', '=', $currentDate)
                                                    ->where('status', '=', 'paid')
                                                    ->where('invoice_type', '=', 'recurring')
                                                    ->distinct('role_user_id')
                                                    ->count('role_user_id');

            // Calculate total daily customers
            $dailyTotalCustomers = $dailyCustomersNewSales + $dailyCustomersRecurringSales;

            // Calculate month-to-date customers from new sales
            $startOfMonth = now()->startOfMonth()->format('Y-m-d H:i:s');
            $endOfMonth = now()->endOfMonth()->format('Y-m-d H:i:s');

            $monthlyCustomersNewSales = Invoice::where('created_at', '>=', $startOfMonth)
                ->where('created_at', '<=', $endOfMonth)
                ->where('status', '=', 'paid')
                ->where('invoice_type', '=', '1st Invoice')
                ->distinct('role_user_id')
                ->count('role_user_id');

            // Calculate month-to-date customers from recurring sales
            $monthlyCustomersRecurringSales = Invoice::where('created_at', '>=', $startOfMonth)
                ->where('created_at', '<=', $endOfMonth)
                ->where('status', '=', 'paid')
                ->where('invoice_type', '=', 'recurring')
                ->distinct('role_user_id')
                ->count('role_user_id');

            // Calculate total month-to-date customers
            $monthlyTotalCustomers = $monthlyCustomersNewSales + $monthlyCustomersRecurringSales;

            


            //From Sales Reps Commission tabels
            // Calculate daily Sales Rep new earnings
            $currentDate = now()->format('Y-m-d');
            $dailySalesRepNewEarnings = Commission::whereDate('created_at', '=', $currentDate)
                                                ->where('commission_type', '=', '1st Invoice')
                                                ->sum('commission_amount');

            // Calculate daily Sales Rep recurring earnings
            $dailySalesRepRecurringEarnings = Commission::whereDate('created_at', '=', $currentDate)
                                                        ->where('commission_type', '=', 'recurring')
                                                        ->sum('commission_amount');

            // Calculate daily total Sales Rep earnings
            $dailyTotalSalesRepEarnings = $dailySalesRepNewEarnings + $dailySalesRepRecurringEarnings;

            // Calculate month-to-date Sales Rep new earnings
            $startOfMonth = now()->startOfMonth()->format('Y-m-d H:i:s');
            $endOfMonth = now()->endOfMonth()->format('Y-m-d H:i:s');

            $monthlySalesRepNewEarnings = Commission::where('created_at', '>=', $startOfMonth)
                ->where('created_at', '<=', $endOfMonth)
                ->where('commission_type', '=', '1st Invoice')
                ->sum('commission_amount');

            // Calculate month-to-date Sales Rep recurring earnings
            $monthlySalesRepRecurringEarnings = Commission::where('created_at', '>=', $startOfMonth)
                ->where('created_at', '<=', $endOfMonth)
                ->where('commission_type', '=', 'recurring')
                ->sum('commission_amount');

            // Calculate month-to-date total Sales Rep earnings
            $monthlyTotalSalesRepEarnings = $monthlySalesRepNewEarnings + $monthlySalesRepRecurringEarnings;


            // Get the list of all Sales Reps
            $allSalesReps = SaleRep::all();
            
            // Get the current date
            $currentDate = now()->format('Y-m-d');

            // Initialize a counter to count Sales Reps without sales
            $salesRepsWithoutSalesCount = 0;

            // Iterate over each Sales Rep
            foreach ($allSalesReps as $salesRep) {
                // Check if there are any commissions recorded for today with commission_type set to '1st Invoice'
                $commission = Commission::where('sales_rep_id', $salesRep->user_id)
                                        ->whereDate('created_at', $currentDate)
                                        ->where('commission_type', '1st Invoice')
                                        ->first();
                
                // If no commission is found, increment the counter
                if (!$commission) {
                    $salesRepsWithoutSalesCount++;
                }
            }

            // Initialize a counter to count Sales Reps with sales
            $salesRepsWithSalesCount = 0;

            // Iterate over each Sales Rep
            foreach ($allSalesReps as $salesRep) {
                // Check if there are any commissions recorded for today with commission_type set to '1st Invoice'
                $commission = Commission::where('sales_rep_id', $salesRep->user_id)
                                        ->whereDate('created_at', $currentDate)
                                        ->where('commission_type', '1st Invoice')
                                        ->first();
                
                // If commission is found, increment the counter
                if ($commission) {
                    $salesRepsWithSalesCount++;
                }
            }

            // Get the total number of registered Sales Reps
            $totalRegisteredSaleReps = SaleRep::count();

            
            // Determine the start and end dates for the period (current month plus previous 6 months)
            $startDate = now()->subMonths(6)->startOfMonth()->format('Y-m-d');
            $endDate = now()->endOfMonth()->format('Y-m-d');

            // Query SaleRep model for registered but not active Sales Reps within the period
            $inactiveSaleRepsCount = SaleRep::whereBetween('created_at', [$startDate, $endDate])
                                            ->whereHas('user', function ($query) {
                                                $query->where('status', '!=', 'active');
                                            })
                                            ->count();

            // Determine the start and end dates for the period (current month plus previous 2 months)
            $startDate = now()->subMonths(2)->startOfMonth()->format('Y-m-d');
            $endDate = now()->endOfMonth()->format('Y-m-d');

            // Query SaleRep model for Sale Reps that changed status from Registered to Active within the period
            $activeSaleRepsCount = SaleRep::whereBetween('created_at', [$startDate, $endDate])
                                        ->whereHas('user', function ($query) {
                                            $query->where('status', 'active');
                                        })
                                        ->count();

            // Determine the start and end dates for the non-performing period (current month plus previous 2 months)
            $startDate = now()->subMonths(2)->startOfMonth()->format('Y-m-d');
            $endDate = now()->endOfMonth()->format('Y-m-d');

            // Get all Sale Reps
            $allSaleReps = SaleRep::all();

            // Initialize a counter for non-performing Sale Reps
            $nonPerformingSaleRepsCount = 0;

            // Iterate over each Sale Rep
            foreach ($allSaleReps as $saleRep) {
                // Check if the Sale Rep has sold any packages during the non-performing period
                $hasSales = Commission::where('sales_rep_id', $saleRep->user_id)
                                    ->whereBetween('created_at', [$startDate, $endDate])
                                    ->exists();
                
                // If the Sale Rep has not sold any packages, increment the counter
                if (!$hasSales) {
                    $nonPerformingSaleRepsCount++;
                }
            }


            // Prepare response data
            $response = [
                'daily_revenue_new_sales' => number_format($dailyRevenueNewSales, 2),
                'daily_revenue_recurring_sales' => number_format($dailyRevenueRecurringSales, 2),
                'daily_total_revenue_sales' => number_format($dailyRevenueNewSales + $dailyRevenueRecurringSales, 2),
                'month_new_sales_mtd_revenue' => number_format($monthlyRevenueNewSales, 2),
                'month_recurring_sales_mtd_revenue' => number_format($monthlyRevenueRecurringSales, 2),
                'month_total_sales_mtd_revenue' => number_format($monthlyTotalRevenue, 2),

                'today_customers_new_sales' => $dailyCustomersNewSales,
                'today_customers_recurring_sales' => $dailyCustomersRecurringSales,
                'today_total_customers' => $dailyTotalCustomers,
                'month_new_sales_mtd_customers' => $monthlyCustomersNewSales,
                'month_recurring_sales_mtd_customers' => $monthlyCustomersRecurringSales,
                'today_recurring_sales_mtd_customers' => $monthlyTotalCustomers,

                'today_sales_rep_new_earnings' => number_format($dailySalesRepNewEarnings, 2),
                'today_sales_rep_recurring_earnings' => number_format($dailySalesRepRecurringEarnings, 2),
                'today_total_sales_rep_earnings' => number_format($dailyTotalSalesRepEarnings, 2),
                'month_new_sales_mtd_sales_rep_earnings' => number_format($monthlySalesRepNewEarnings, 2),
                'month_recurring_sales_mtd_sales_rep_earnings' => number_format($monthlySalesRepRecurringEarnings, 2),
                'today_recurring_sales_mtd_sales_rep_earnings' => number_format($monthlyTotalSalesRepEarnings, 2),
                
                'total_sales_rep_no_sales' => $salesRepsWithoutSalesCount,
                'today_sales_rep_with_sales' => $salesRepsWithSalesCount,
                'total_registered_sale_reps' => $totalRegisteredSaleReps,
                'total_inactive_sale_reps' => $inactiveSaleRepsCount,
                'total_active_sale_reps' => $activeSaleRepsCount,
                'total_non_performing_sale_reps' => $nonPerformingSaleRepsCount,

            ];

            // Return response as JSON
            return response()->json($response);
        }

        public function getDailyRevenueData(Request $request)
        {
            // Define start and end dates based on request parameters or default to the last 7 days
            $startDate = $request->input('start_date', now()->subDays(6)->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
        
            // Generate all dates within the specified range
            $datesInRange = [];
            $currentDate = Carbon::parse($startDate);
            $endDateObj = Carbon::parse($endDate);
        
            while ($currentDate->lte($endDateObj)) {
                $datesInRange[] = $currentDate->format('Y-m-d');
                $currentDate->addDay();
            }
        
            // Query database to get daily revenue data for the specified period
            $dailyRevenueData = Invoice::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'paid')
                ->selectRaw('DATE(created_at) AS date, 
                            SUM(CASE WHEN invoice_type = "1st Invoice" THEN total_amount ELSE 0 END) AS new_sales, 
                            SUM(CASE WHEN invoice_type = "recurring" THEN total_amount ELSE 0 END) AS recurring_sales, 
                            SUM(total_amount) AS total_revenue')
                ->groupBy('date', 'created_at')
                ->orderBy('date')
                ->get();
        
            // Prepare data for each series
            $newSalesData = $dailyRevenueData->pluck('new_sales', 'date')->toArray();
            $recurringSalesData = $dailyRevenueData->pluck('recurring_sales', 'date')->toArray();
            $totalRevenueData = $dailyRevenueData->pluck('total_revenue', 'date')->toArray();
        
            // Prepare response data
            $series = [
                [
                    'name' => 'Daily Revenue New Sales',
                    'data' => array_map(function ($date) use ($newSalesData) {
                        return $newSalesData[$date] ?? 0;
                    }, $datesInRange),
                ],
                [
                    'name' => 'Daily Revenue Recurring Sales',
                    'data' => array_map(function ($date) use ($recurringSalesData) {
                        return $recurringSalesData[$date] ?? 0;
                    }, $datesInRange),
                ],
                [
                    'name' => 'Daily Total Revenue',
                    'data' => array_map(function ($date) use ($totalRevenueData) {
                        return $totalRevenueData[$date] ?? 0;
                    }, $datesInRange),
                ],
            ];
        
            // Return response as JSON
            return response()->json([
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'series' => $series,
                'dates' => $datesInRange,
            ]);
        }
        


        public function getDailySalesRepData(Request $request)
        {
            // Define start and end dates based on request parameters or default to the last 7 days
            $startDate = $request->input('start_date', now()->subDays(6)->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));

            // Generate all dates within the specified range
            $datesInRange = [];
            $currentDate = Carbon::parse($startDate);
            $endDateObj = Carbon::parse($endDate);

            while ($currentDate->lte($endDateObj)) {
                $datesInRange[] = $currentDate->format('Y-m-d');
                $currentDate->addDay();
            }

            // Query database to get daily sales rep data for the specified period
            $dailySalesRepData = Commission::whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) AS date, 
                            SUM(CASE WHEN commission_type = "1st Invoice" THEN commission_amount ELSE 0 END) AS new_earnings, 
                            SUM(CASE WHEN commission_type = "recurring" THEN commission_amount ELSE 0 END) AS recurring_earnings')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Query database to get daily total customers data for the specified period
            $dailyTotalCustomersData = Customer::whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) AS date, COUNT(*) AS total_customers')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Extract specific values for daily sales rep data
            $dailySalesRepNewEarnings = [];
            $dailySalesRepRecurringEarnings = [];
            foreach ($datesInRange as $date) {
                $data = $dailySalesRepData->firstWhere('date', $date);
                $dailySalesRepNewEarnings[] = $data ? $data->new_earnings : 0;
                $dailySalesRepRecurringEarnings[] = $data ? $data->recurring_earnings : 0;
            }

            // Extract specific values for daily total customers data
            $dailyTotalCustomers = [];
            foreach ($datesInRange as $date) {
                $data = $dailyTotalCustomersData->firstWhere('date', $date);
                $dailyTotalCustomers[] = $data ? $data->total_customers : 0;
            }

            // Prepare response data
            $response = [
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
                'series' => [
                    [
                        'name' => 'Daily Sale Rep New Earnings',
                        'data' => $dailySalesRepNewEarnings,
                    ],
                    [
                        'name' => 'Daily Sale Rep Recurring Earnings',
                        'data' => $dailySalesRepRecurringEarnings,
                    ],
                    [
                        'name' => 'Daily Total Customers',
                        'data' => $dailyTotalCustomers,
                    ],
                ],
                'dates' => $datesInRange,
            ];

            // Return response as JSON
            return response()->json($response);
        }






}
