<?php

namespace App\Http\Controllers;
use App\Models\Subscription;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Payment;
use App\Models\Commission;
use App\Models\SaleRep;
use App\Models\PerformanceNumber;
use App\Models\User;
use App\Models\Invoice;
use Illuminate\Http\Request;

class DashboardController extends Controller
{

    public function total_stats()
    {
        // Total Subscription
        $t_subscription = Subscription::count();

        // Total Sales Reps
        $t_salesreps_yet = User::whereHas('roles', function ($query) {
            $query->where('name', 'salesreps');
        })->count();


        // Total Customers
        $t_customer_yet = User::whereHas('roles', function ($query) {
            $query->where('name', 'customer');
        })->count();


        // New Customers in the Current Month
        $firstDayOfMonth = date('Y-m-01');
        $currentDate = date('Y-m-d');

        $currentMonthNewCustomers = User::whereHas('roles', function ($query) {
            $query->where('name', 'customer');
        })->whereBetween('users.created_at', [$firstDayOfMonth, $currentDate])->count();

        //recurring_customer_counter
        $recurringCustomers = User::whereHas('roles', function ($query) {
            $query->where('name', 'customer');
        })->whereHas('subscriptions', function ($query) use ($firstDayOfMonth, $currentDate) {
            $query->whereBetween('subscriptions.created_at', [$firstDayOfMonth, $currentDate]);
        })->has('subscriptions', '>', 1)->count();


        // $currentMonth = date('Y-m'); // Current month in the 'Y-m' format
        $today = date('Y-m-d'); // Current date in the 'Y-m-d' format
        $totalCommission = Commission::whereDate('created_at', $today)->sum('commission_amount');



        // Return raw data without additional processing
        return response()->json([
            't_subscription' => $t_subscription,
            't_salesreps_yet' => $t_salesreps_yet,
            't_customer_yet' => $t_customer_yet,
            'current_month_new_customers' => $currentMonthNewCustomers,
            'recurring_customers' => $recurringCustomers,
            'today_total_salesRep_commission' => $totalCommission,
            // Add other counters here
        ]);

    }


}
