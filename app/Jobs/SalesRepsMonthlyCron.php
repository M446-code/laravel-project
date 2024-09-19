<?php

namespace App\Jobs;

use App\Models\Commission;
use App\Models\PerformanceNumber;
use App\Models\User;
use App\Models\Setting;
use App\Models\Invoice;
use App\Models\InvoiceSr;
use App\Models\Payout;
use App\Models\SaleRep;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psy\Readline\Hoa\Console;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class SalesRepsMonthlyCron implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Fetch users with the 'salesreps' role
        $salesReps = User::whereHas('roles', function ($query) {
            $query->where('name', 'salesreps');
        })->get();

        $responses = [];
        $salesRepPayoutsList = [];

        foreach ($salesReps as $salesRep) {
            $salesRepcreated_at = $salesRep->created_at;
            $sum = date('n', strtotime($salesRepcreated_at)) + Setting::where('key', 'default_onboarding_period')->value('value') + 1;
            $formattedDate = date("Y-m-01", strtotime("2024-$sum-01"));
            $current_date = date("Y-m-d");

            if ($current_date <= $formattedDate) {
                $this->generateInvoice($salesRep->id, now()->subMonth()->format('Y-m'), $salesRep->status);
            } else {
                // Dispatch the CheckPerformanceJob for each sales rep
                $response = $this->checkPerformance($salesRep->id, now()->subMonth()->format('Y-m'), $salesRep->status);
                $responses[] = $response->original; // Assuming $response is a response instance
            }
        }

        // get the invoices
        $invoices = InvoiceSr::where('role', 'salesreps')->where('status', 'unpaid')->get();

        // I need sales rep id, email & invoice amount in the array
        foreach ($invoices as $invoice) {

            $salesRepPayoutsList[] = [
                'sales_rep_id' => $invoice->role_user_id,
                'email' => $invoice->salesReps->paypal_account,
                'amount' => $invoice->total_amount,
                'invoice_id' => $invoice->id,
                'sales_reps_status' => $invoice->user_status,
            ];
        }

        // make payout
        $pyoutBatchId = $this->makePayout($salesRepPayoutsList);

        // update invoice status
        InvoiceSr::where('role', 'salesreps')
            ->where('status', 'unpaid')
            ->update(['transaction_id' => $pyoutBatchId]);

        return response()->json(['responses' => $responses], 200);
    }

    protected function checkPerformance($salesRepId, $month, $salesRepStatus)
    {
        // Query the Commissions table to calculate the number of new customers for the specified sales rep and month
        $commissionCount = Commission::where('sales_rep_id', $salesRepId)
            ->where('month', $month)
            ->where('paid', 0)
            ->count();

        // Retrieve the performance number for the sales rep
        $performanceNumber = PerformanceNumber::where('sales_rep_id', $salesRepId)->first();

        if (!$performanceNumber) {
            return response()->json(['message' => 'Performance number not found for the sales rep'], 404);
        }

        $requiredPerformance = $performanceNumber->performance_number;

        // Compare the number of new customers with the performance number
        if ($commissionCount >= $requiredPerformance) {
            // The sales rep meets the performance criteria
            // Here will be the invoice generate 
            $this->generateInvoice($salesRepId, $month, $salesRepStatus);
            return response()->json(['message' => 'Sales rep meets the performance criteria & Invoice Generated'], 200);
        } else {
            // The sales rep does not meet the performance criteria
            // Check if this is the second subsequent non-performing month
            $previousMonth = date('Y-m', strtotime("-1 month", strtotime($month)));
            $previousCommissionCount = Commission::where('sales_rep_id', $salesRepId)
                ->where('month', $previousMonth)
                ->count();

            if ($previousCommissionCount < $requiredPerformance) {
                // This is the second subsequent non-performing month
                // Update the SalesRep record with a "non-performing" status
                $salesRep = User::find($salesRepId);
                // $salesRep->update(['status' => 'non-performing']);
                $this->generateInvoice($salesRepId, $month, $salesRepStatus);
                return response()->json(['message' => 'Sales rep marked as non-performing & Invoice Generated'], 200);
            } else {
                // If it's not the second subsequent non-performing month, check for the third month calculation
                $thirdMonth = date('Y-m', strtotime("-2 months", strtotime($month)));
                $firstNonPerformingMonth = date('Y-m', strtotime("-2 months", strtotime($previousMonth)));

                $firstNonPerformingCustomers = Commission::where('sales_rep_id', $salesRepId)
                    ->where('month', $firstNonPerformingMonth)
                    ->count();

                $thirdMonthCalculation = ($requiredPerformance - $firstNonPerformingCustomers) +
                    ($requiredPerformance - $commissionCount) +
                    $requiredPerformance;

                if ($thirdMonthCalculation >= 0) {
                    // Update the SalesRep record with a "Regular Status"
                    $salesRep = User::find($salesRepId);
                    $salesRep->update(['status' => 'Active']);
                    $this->generateInvoice($salesRepId, $month, $salesRepStatus);
                    return response()->json([
                        'message' => 'Sales rep marked as regular in the third month & Invoice Generated',
                        'thirdMonthCalculation' => $thirdMonthCalculation,
                        'firstNonPerformingCustomers' => $firstNonPerformingCustomers,
                        'commissionCount' => $commissionCount,
                        'requiredPerformance' => $requiredPerformance
                    ], 200);
                }

                // If the third-month calculation is not met, do nothing for now
                return response()->json(['message' => 'Sales rep did not meet the performance criteria in the third month'], 200);
            }
        }
    }

    protected function generateInvoice($salesRepId, $month, $salesRepStatus)
    {
        // Calculate the total commission amount for the specified month
        $totalCommission = Commission::where('sales_rep_id', $salesRepId)
            ->where('month', $month)
            ->where('paid', 0)
            ->sum('commission_amount');

        // Check if the total commission is greater than 0
        if ($totalCommission > 0) {

            // Create an invoice record with the total commission amount and status 'unpaid'
            $invoice = InvoiceSr::create([
                'role' => 'salesreps',
                'role_user_id' => $salesRepId,
                'user_status' => $salesRepStatus,
                'date' => date('Y-m-d'),
                'month' => $month,
                'total_amount' => $totalCommission,
                'status' => 'unpaid', // Set the initial status to 'unpaid'
                // Add other necessary fields
            ]);

            // Update the associated commissions to mark them as paid
            Commission::where('sales_rep_id', $salesRepId)
                ->where('month', $month)
                ->where('paid', 0)
                ->update(['paid' => 1]);

            // Perform any additional actions related to invoice generation
            // For example, sending email notifications, updating other records, etc.

            return $invoice;
        } else {
            // Log or handle the case when $totalCommission is 0
            // For example, you might want to skip invoice creation and updates
            // or log a message for future reference.

            // Return null or false, depending on your needs
            return null;
        }
    }


    protected function makePayout($salesRepPayoutsList)
    {
        $pyoutBatchId = null;

        // items
        $items = [];
        foreach ($salesRepPayoutsList as $salesRepPayout) {
            // Log the data for each iteration
            // Log::info('Sales Rep Payout Data: ' . json_encode($salesRepPayout));

            // Ensure the email and amount fields are not empty before adding to the items array
            if (!empty($salesRepPayout['email']) && !empty($salesRepPayout['amount'])) {
                $items[] = [
                    'recipient_type' => 'EMAIL',
                    'amount' => [
                        'value' => $salesRepPayout['amount'],
                        'currency' => 'USD',
                    ],
                    'receiver' => $salesRepPayout['email'],
                    'note' => 'Thank you for your service!',
                    'sender_item_id' => $salesRepPayout['invoice_id'],
                ];
            }
        }

        // Check if items array is not empty before proceeding
        if (!empty($items)) {
            // Replace this with the actual details of your payout
            $payoutData = [
                'sender_batch_header' => [
                    'email_subject' => 'You have received a payment from IKY!',
                    'note' => 'Thank you for your service!',
                ],
                'items' => $items,
            ];

            // Log payout data for debugging
            // Log::info('Payout Data: ' . json_encode($payoutData));

            // Continue with the payout
            try {
                $provider = new PaypalClient;
                $provider->getAccessToken();

                $response = $provider->createBatchPayout($payoutData);

                // Check if the response contains the necessary information
                if (isset($response['batch_header']) && isset($response['batch_header']['payout_batch_id'])) {
                    $payoutBatchId = $response['batch_header']['payout_batch_id'];
                    // $payoutStatus = $response['batch_header']['batch_status'];

                    // get batch details
                    $payoutBatchDetails = $provider->showBatchPayoutDetails($payoutBatchId);

                    $payout_items = $payoutBatchDetails['items'];
                    // Add the code to create Payout record after successful payout
                    foreach ($salesRepPayoutsList as $salesRepPayout) {
                        // get payout_item where salesRepPayout['email'] == payout_item['receiver']
                        $payout_item = array_filter($payout_items, function ($item) use ($salesRepPayout) {
                            return $item['payout_item']['sender_item_id'] == $salesRepPayout['invoice_id'];
                        });

                        $payout_item = array_values($payout_item);

                        Payout::create([
                            'role' => 'salesreps',
                            'role_user_id' => $salesRepPayout['sales_rep_id'],
                            'invoice_id' => $salesRepPayout['invoice_id'],
                            'month' => now()->subMonth()->format('Y-m'),
                            'amount' => $salesRepPayout['amount'],
                            'payout_item_id' => $payout_item['0']['payout_item_id'],
                            'paypal_transaction_id' => $payoutBatchId,
                            'result_type' => $payout_item['0']['transaction_status'],
                            'sale_reps_status' => $salesRepPayout['sales_reps_status'],
                            // Add other necessary fields
                            'payout_batch_id' => $payoutBatchId,
                        ]);
                    }
                } else {
                    Log::error('Payout response is missing required information.');
                }
                return $payoutBatchId;
            } catch (\Exception $e) {
                Log::error('Payout failed: ' . $e->getMessage());
            }
        } else {
            Log::error('No valid items for payout.');
        }
    }

    protected function getAccessToken()
    {


        $provider = new PaypalClient;
        $accessToken = $provider->getAccessToken();

        return $accessToken;
    }
}
