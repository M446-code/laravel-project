<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    //

    public function getCustomerPayment()
    {
        // Get the authenticated user
        $user = auth()->user();

        // Retrieve the subscriptions with package details
        $payments = Payment::where('customer_id', $user->id)->get();


        return response()->json($payments);
    }

    public function getAllCustomerPayments()
    {
        // Retrieve all payments with customer details
        $payments = Payment::with('customer')->get();

        // If you want to transform the data, you can use the map function
        $result = $payments->map(function ($payment) {
            if ($payment->customer) {
                $customerData = $payment->customer->name;
            } else {
                // Handle the case where the customer is not found
                $customerData = null;
            }

            $paymentData = $payment->toArray();

            return array_merge($paymentData, ['customer' => $customerData]);
        });

        return response()->json($result);
    }


    public function getSinglePayment($id)
    {
        $payment = Payment::with('customer')->find($id);

        if ($payment) {
            $customerData = $payment->customer->name;
            $paymentData = $payment->toArray();

            $result = array_merge($paymentData, ['customer' => $customerData]);
            return response()->json($result);
        }

        return response()->json(['error' => 'Payment not found.'], 404);
    }

    public function getPaymentsAllCustomer(Request $request)
    {
        $perPage = $request->input('perPage', 25);
        $startDate = $request->input('startDate', Carbon::now()->subDays(7)->toDateString());
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay(1)->toDateString();

        $customers = DB::table('payments')
            ->join('users', 'payments.customer_id', '=', 'users.id')
            ->join('customers', 'payments.customer_id', '=', 'customers.user_id')
            ->join('invoices', 'payments.id', '=', 'invoices.transaction_id') // New join for invoices table
            ->whereBetween('payments.created_at', [$startDate, $endDate])
            ->select(
                'payments.id',
                'payments.description',
                'payments.amount',
                'payments.payment_type',
                'payments.subscription_id',
                'payments.payment_method_id',
                'payments.transaction_id',
                'payments.result_type',
                'payments.created_at as payment_created_at',
                'payments.updated_at as payment_updated_at',
                'customers.user_id',
                'customers.client_id',
                'customers.business_name',
                'customers.street',
                'customers.zipCode',
                'customers.country',
                'customers.state',
                'customers.city',
                'customers.referral_username',
                'customers.payment_method',
                'customers.created_at',
                'customers.updated_at',
                'users.name as name',
                'users.email as email',
                'users.phone as phone',
                'users.status as status',
                // 'invoices.id',
                // 'invoices.role',
                // 'invoices.role_user_id',
                // 'invoices.user_status',
                // 'invoices.date',
                // 'invoices.month',
                // 'invoices.recurring_amount',
                // 'invoices.setup_fee',
                // 'invoices.total_amount',
                // 'invoices.invoice_type',
                // 'invoices.transaction_id as invoice_transaction_id', // Alias for invoices.transaction_id
                // 'invoices.paypal_subscription_id',
                // 'invoices.status',
                // 'invoices.created_at as invoice_created_at',
                // 'invoices.updated_at as invoice_updated_at'

            )
            ->orderBy('payments.created_at', 'desc')
            ->paginate($perPage);

        return response()->json($customers);
    }

    public function getPaymentsAllSalesreps(Request $request)
    {
        $perPage = $request->input('perPage', 25);
        $startDate = $request->input('startDate', Carbon::now()->subDays(7)->toDateString());
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay()->toDateString();

        $payouts = DB::table('payouts')
            ->where('payouts.role', "salesreps")
            ->join('users', 'payouts.role_user_id', '=', 'users.id')
            ->join('sale_reps', 'payouts.role_user_id', '=', 'sale_reps.user_id')
            ->join('invoices_srs', 'payouts.paypal_transaction_id', '=', 'invoices_srs.transaction_id')
            ->leftJoin('performance_numbers', 'payouts.role_user_id', '=', 'performance_numbers.sales_rep_id')
            ->whereBetween('payouts.created_at', [$startDate, $endDate])
            ->select(
                'payouts.id',
                'payouts.role',
                'payouts.role_user_id',
                'payouts.invoice_id',
                'payouts.amount',
                'payouts.paypal_transaction_id',
                'payouts.result_type',
                'payouts.sale_reps_status',
                'payouts.created_at as payout_created_at',
                'payouts.updated_at as payout_updated_at',
                'invoices_srs.id  as invoice_id',
                'invoices_srs.role',
                'invoices_srs.role_user_id',
                'invoices_srs.user_status',
                'invoices_srs.date',
                'invoices_srs.month',
                'invoices_srs.recurring_amount',
                'invoices_srs.setup_fee',
                'invoices_srs.total_amount',
                'invoices_srs.invoice_type',
                'invoices_srs.transaction_id',
                'invoices_srs.paypal_subscription_id',
                'invoices_srs.status',
                'invoices_srs.created_at as invoice_created_at',
                'invoices_srs.updated_at as invoice_updated_at',
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
            ->orderBy('payouts.created_at', 'desc')
            ->paginate($perPage);

        return response()->json($payouts);
    }
}
