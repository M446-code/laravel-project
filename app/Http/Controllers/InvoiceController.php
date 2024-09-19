<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    //

    // show
    // public function show($id)
    // {
    //     $invoice = Invoice::find($id);
    //     if (!$invoice) {
    //         return response()->json(['message' => 'Invoice not found'], 404);
    //     }
    //     return response()->json($invoice);
    // }

    public function getInvoicesAllCustomer(Request $request)
    {
        $perPage = $request->input('perPage', 500);
        $startDate = $request->input('startDate', Carbon::now()->subDays(7)->toDateString());
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay()->toDateString();

        $invoices = DB::table('invoices')
            ->where('role', "customer")
            ->join('users', 'invoices.role_user_id', '=', 'users.id')
            ->join('customers', 'invoices.role_user_id', '=', 'customers.user_id')
            ->join('payments', 'invoices.transaction_id', '=', 'payments.id')     // new update
            ->join('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
            ->join('packages', 'subscriptions.package_id', '=', 'packages.id')
            ->whereBetween('invoices.created_at', [$startDate, $endDate])
            ->select(
                'invoices.id',
                'invoices.role',
                'invoices.role_user_id',
                'invoices.user_status',
                'invoices.date',
                'invoices.month',
                'invoices.recurring_amount',
                'invoices.setup_fee',
                'invoices.total_amount',
                'invoices.invoice_type',
                'invoices.transaction_id',
                'invoices.paypal_subscription_id',
                'invoices.status',
                'invoices.created_at as invoice_created_at',
                'invoices.updated_at as invoice_updated_at',
                'payments.id as payment_id',
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
                'packages.title as package_title'
            )
            ->orderBy('invoices.created_at', 'desc')
            ->paginate($perPage);

        return response()->json($invoices);
    }

    public function getInvoiceForCustomer($userId, $invoiceId)
    {
        $invoice = DB::table('invoices')
            ->where('invoices.role_user_id', $userId)
            ->where('invoices.id', $invoiceId)
            ->where('role', 'customer')
            ->join('users', 'invoices.role_user_id', '=', 'users.id')
            ->join('customers', 'invoices.role_user_id', '=', 'customers.user_id')
            ->join('payments', 'invoices.transaction_id', '=', 'payments.id')
            ->join('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
            ->join('packages', 'subscriptions.package_id', '=', 'packages.id')
            ->select(
                'invoices.id',
                'invoices.role',
                'invoices.role_user_id',
                'invoices.user_status',
                'invoices.date',
                'invoices.month',
                'invoices.recurring_amount',
                'invoices.setup_fee',
                'invoices.total_amount',
                'invoices.invoice_type',
                'invoices.transaction_id',
                'invoices.paypal_subscription_id',
                'invoices.status',
                'invoices.created_at as invoice_created_at',
                'invoices.updated_at as invoice_updated_at',
                'payments.id as payment_id',
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
                'packages.title as package_title'
            )
            ->first(); // Use 'first()' to get a single record

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found for the specified user and invoice ID'], 404);
        }

        return response()->json($invoice);
    }



    public function getInvoicesAllSalesreps(Request $request)
    {
        $perPage = $request->input('perPage', 500);
        $startDate = $request->input('startDate', Carbon::now()->subDays(7)->toDateString());
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay()->toDateString();

        $invoices = DB::table('invoices_srs')
            ->where('invoices_srs.role', 'salesreps') // Specify the table name or alias
            ->join('users', 'invoices_srs.role_user_id', '=', 'users.id')
            ->join('payouts', 'invoices_srs.transaction_id', '=', 'payouts.paypal_transaction_id')
            ->join('sale_reps', 'invoices_srs.role_user_id', '=', 'sale_reps.user_id')
            ->leftJoin('performance_numbers', 'invoices_srs.role_user_id', '=', 'performance_numbers.sales_rep_id')
            ->whereBetween('invoices_srs.created_at', [$startDate, $endDate])
            ->select(
                'invoices_srs.id',
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
                'payouts.id as payout_id',
                'payouts.invoice_id',
                'payouts.amount',
                'payouts.paypal_transaction_id',
                'payouts.result_type',
                'payouts.sale_reps_status',
                'payouts.created_at as payout_created_at',
                'payouts.updated_at as payout_updated_at',
                'sale_reps.id',
                'users.id as user_id',
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
                // 'sale_reps.created_at',
                // 'sale_reps.updated_at',
                'performance_numbers.performance_number as performance_number'
            )
            ->orderBy('invoices_srs.created_at', 'desc')
            ->paginate($perPage);

        return response()->json($invoices);
    }

    public function getInvoiceForSalesRep($userId, $invoiceId)
    {
        $invoice = DB::table('invoices_srs')
            ->where('invoices_srs.role_user_id', $userId)
            ->where('invoices_srs.id', $invoiceId)
            ->where('invoices_srs.role', 'salesreps')
            ->join('users', 'invoices_srs.role_user_id', '=', 'users.id')
            ->join('payouts', 'invoices_srs.transaction_id', '=', 'payouts.paypal_transaction_id')
            ->join('sale_reps', 'invoices_srs.role_user_id', '=', 'sale_reps.user_id')
            ->leftJoin('performance_numbers', 'invoices_srs.role_user_id', '=', 'performance_numbers.sales_rep_id')
            ->select(
                'invoices_srs.id',
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
                'payouts.id as payout_id',
                'payouts.invoice_id',
                'payouts.amount',
                'payouts.paypal_transaction_id',
                'payouts.result_type',
                'payouts.sale_reps_status',
                'payouts.created_at as payout_created_at',
                'payouts.updated_at as payout_updated_at',
                'sale_reps.id',
                'users.id as user_id',
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
            ->first(); // Use 'first()' to get a single record

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found for the specified sales rep and invoice ID'], 404);
        }

        return response()->json($invoice);
    }
}
