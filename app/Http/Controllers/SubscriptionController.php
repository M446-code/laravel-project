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
use App\Models\InvoiceSr;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class SubscriptionController extends Controller
{

    //Get the a single customer subscriptions object from subscriptions table join with Packages, Users, Sale_reps,
    // REG.ID
    // BUSINESS NAME
    // EMAIL	
    // PACKAGE	LAST INVOICE & PAYMENT	
    // NEXT PAYMENT	
    // FURTHER MONTHS	
    // SALES REPS


    public function get_single_customer_subscriptions_list($user_id)
    {
        // Retrieve the subscriptions with package details, latest invoice, and latest payment
        $subscriptions = Subscription::with(['package', 'invoice', 'payment'])
            ->with('customer')
            ->where('customer_id', $user_id)
            ->get();

        $formattedSubscriptions = $subscriptions->map(function ($subscription) {
            // Retrieve the latest invoice and latest payment
            $latestInvoice = $subscription->invoice->sortByDesc('created_at')->first();
            $latestPayment = $subscription->payment->sortByDesc('created_at')->first();

            // Calculate the number of months due
            $termMonths = optional($subscription->package)->term_months;
            $totalPayments = $subscription->payment->count();
            $monthsDue = max(0, $termMonths - $totalPayments);

            $nextPaymentDate = null;

            // Calculate next payment date if both invoice and payment are available
            if ($latestInvoice && $latestPayment) {
                // Get the subscription start date
                $subscriptionStartDate = $latestInvoice->created_at;

                // Calculate the next payment date
                $nextPaymentDate = $subscriptionStartDate->copy()->addMonths(1)->startOfMonth();

                // Adjust the day if necessary
                if ($nextPaymentDate->day > $subscriptionStartDate->day) {
                    $nextPaymentDate->day = $subscriptionStartDate->day;
                } else {
                    // Handle the case where the next month has fewer days
                    $nextPaymentDate->day = min($subscriptionStartDate->day, $nextPaymentDate->daysInMonth);
                }
            }

            return [
                'subscription_id' => $subscription->id,
                'subscription_status' => $subscription->status,
                'subscription_on' => $subscription->created_at ? $subscription->created_at->format('Y-m-d \T H:i:s') : null,
                'package_id' => optional($subscription->package)->id,
                'package_title' => optional($subscription->package)->title,
                'package_description' => optional($subscription->package)->description,
                'package_monthly_price' => optional($subscription->package)->monthly_price,
                'package_setup_cost' => optional($subscription->package)->setup_cost,
                'package_term_months' => $termMonths,
                'package_status' => optional($subscription->package)->status,
                'invoice_id' => optional($latestInvoice)->id,
                'invoice_create_at' => optional($latestInvoice)->created_at ? optional($latestInvoice)->created_at->format('Y-m-d \T H:i:s') : null,
                'invoice_date' => optional($latestInvoice)->date,
                'invoice_amount' => optional($latestInvoice)->total_amount,
                'payment_date' => optional($latestPayment)->created_at ? optional($latestPayment)->created_at->format('Y-m-d \T H:i:s') : null,
                'payment_result_type' => optional($latestPayment)->result_type,
                'next_payment_date' => $nextPaymentDate ? $nextPaymentDate->format('Y-m-d \T H:i:s') : null,
                'further_months_due' => $monthsDue,
                'client_id' => optional($subscription->customer)->client_id,
                'customer_user_id' => optional($subscription->customer)->user_id,
                'customer_reg_date' => optional($subscription->customer)->created_at ? optional($subscription->customer)->created_at->format('Y-m-d \T H:i:s') : null,
                'customer_business_name' => optional($subscription->customer)->business_name,
            ];
        });

        return response()->json($formattedSubscriptions);
    }

    public function getCustomerSubscription()
    {
        // Get the authenticated user
        $user = auth()->user();

        // Retrieve the subscriptions with package details
        $subscriptions = Subscription::where('customer_id', $user->id)
            ->with('package') // Eager load the package relationship
            ->get();

        $formattedSubscriptions = $subscriptions->map(function ($subscription) {
            $statusText = $subscription->status ? 'Active' : 'Suspended';

            return [
                'status' => $statusText,
                'package_id' => $subscription->package->id,
                'package_description' => $subscription->package->description,
                'package_title' => $subscription->package->title,
                'package_monthly_price' => $subscription->package->monthly_price,
                'package_setup_cost' => $subscription->package->setup_cost,
                'package_term_months' => $subscription->package->term_months,
            ];
        });

        return response()->json($formattedSubscriptions);
    }

    public function getAllCustomerSubscriptions()
    {

        // Retrieve the subscriptions with package details
        $subscriptions = Subscription::with('package') // Eager load the package relationship
            ->get();

        $formattedSubscriptions = $subscriptions->map(function ($subscription) {
            $statusText = $subscription->status ? 'Active' : 'Inactive';

            return [
                'status' => $statusText,
                'package_id' => $subscription->package->id,
                'package_title' => $subscription->package->title,
                'package_description' => $subscription->package->description,
                'package_monthly_price' => $subscription->package->monthly_price,
                'package_setup_cost' => $subscription->package->setup_cost,
                'package_term_months' => $subscription->package->term_months,
            ];
        });

        return response()->json($formattedSubscriptions);
    }

    //subcribe
    public function subscribePackage(Request $request)
    {
        // Get the authenticated user (customer)
        $customer = auth()->user();
    
        $selectedPackageId = $request->input('selected_package_id');
        $package = Package::find($selectedPackageId);
    
        // Initialize product IDs with the selected package ID
        $productIds = [$selectedPackageId];
    
        // If the selected package is Real-Time Presence Accelerator (4114), include Yelp Public Add/Edit (4125)
        if ($selectedPackageId == 4114) {
            $productIds[] = 4125;
        }
    
        // Update Customer and Advice Local Client Status
        $alOrder = null;
        if ($package->is_advice_local_enabled) {
            // Optionally update the status of Advice Local client if applicable
            $alOrder = $this->updateAdviceLocalClientStatus($customer->customer->client_id, $customer->id, $productIds);
        }
    
        // Save subscription details in the Subscription table
        $subscription = new Subscription();
        $subscription->customer_id = $customer->id;
        $subscription->package_id = $selectedPackageId;
        $subscription->paypal_subscription_id = $request->input('paypal_subscription_id');
        $subscription->advice_local_order_id = $alOrder != null ? $alOrder['data']['order']['id'] : null;
        $subscription->salesrep_commission = $this->calculateSalesRepCommission($customer);
        $subscription->save();
    
        // Save payment details in the Payment table
        $payment = new Payment();
        $payment->payment_type = '1st Month';
        $payment->transaction_id = $request->input('transaction_id');
        $payment->description = $request->input('description');
        $payment->amount = $request->input('amount') + $request->input('setup_cost');
        $payment->subscription_id = $subscription->id;
        $payment->customer_id = $customer->id;
        $payment->payment_method_id = $request->input('payment_method_id');
        $payment->paypal_subscription_id = $request->input('paypal_subscription_id');
        $payment->save();
    
        // Create an invoice for the subscription
        $invoice = new Invoice();
        $invoice->role = 'customer'; 
        $invoice->role_user_id = $customer->id;
        $invoice->user_status = "Active";
        $invoice->date = now()->toDateString();
        $invoice->month = now()->format('Y-m');
        $invoice->recurring_amount = $request->input('amount');
        $invoice->setup_fee = $request->input('setup_cost');
        $invoice->total_amount = $request->input('amount') + $request->input('setup_cost');
        $invoice->invoice_type = '1st Invoice';
        $invoice->transaction_id = $payment->id;
        $invoice->paypal_subscription_id = $request->input('paypal_subscription_id');
        $invoice->status = 'paid'; 
        $invoice->save();
    
        // Calculate commissions for the associated sales rep
        $month = now()->format('Y-m');
        $packagePrice = $request->input('amount') + $request->input('setup_cost');
        $this->calculateCommissions($customer->customer->referral_username, $month, $packagePrice, $customer->id);
    
        return response()->json(['message' => 'Subscription, Payment, and Commissions Processed Successfully']);
    }
    

    public function calculateCommissions($salesRepUsername, $month, $packagePrice, $customerId)
    {
        // Find the sales rep by username
        $salesRep = SaleRep::where('username', $salesRepUsername)->first();

        if (!$salesRep) {
            return response()->json(['message' => 'Sales rep not found'], 404);
        }

        // Calculate the commission as 10% of the package price
        $commissionAmount = $packagePrice * ($salesRep->commission / 100); // 10% commission

        // sum of commission_amount
        $sumOfCommissionAmount = Commission::where('sales_rep_id', $salesRep->user_id)
            ->sum('commission_amount');
        // sum of deduction
        $sumOfDeduction = Commission::where('sales_rep_id', $salesRep->user_id)
            ->sum('deduction');

        // calculate the  balance
        $calculatedBalance = $sumOfCommissionAmount + $commissionAmount - $sumOfDeduction;

        // Create a new commission record in the Commissions table
        $commission = new Commission([
            'sales_rep_id' => $salesRep->user_id,
            'month' => $month,
            'commission_type' => '1st Invoice',
            'commission_amount' => $commissionAmount,
            'deduction' => 0,
            'balance' => $calculatedBalance,
            'paid' => false, // Assuming commissions are initially unpaid
            'customer_id' => $customerId,
        ]);
        $commission->save();

        return response()->json(['message' => 'Commission calculated and recorded'], 201);
    }

    public function checkPerformance($salesRepId, $month)
    {
        // Query the Commissions table to calculate the number of new customers for the specified sales rep and month
        $commissionCount = Commission::where('sales_rep_id', $salesRepId)
            ->where('month', $month)
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
            //Here will be the invoice generate 
            $this->generateInvoice($salesRepId, $month);
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
                $salesRep->update(['status' => 'non-performing']);
                $this->generateInvoice($salesRepId, $month);
                return response()->json(['message' => 'Sales rep marked as non-performing & Invoice Generated'], 200);
            }

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
                $salesRep->update(['status' => 'regular']);
                $this->generateInvoice($salesRepId, $month);
                return response()->json(['message' => 'Sales rep marked as regular in the third month & Invoice Generated', 'thirdMonthCalculation' => $thirdMonthCalculation, 'firstNonPerformingCustomers' => $firstNonPerformingCustomers, 'commissionCount' => $commissionCount, 'requiredPerformance' => $requiredPerformance], 200);
            }

            // If the third-month calculation is not met, do nothing for now
            return response()->json(['message' => 'Sales rep did not meet the performance criteria in the third month'], 200);
        }
    }


    public function generateInvoice($salesRepId, $month)
    {
        // Calculate the total commission amount for the specified month
        $totalCommission = Commission::where('sales_rep_id', $salesRepId)
            ->where('month', $month)
            ->sum('commission_amount');

        // Create an invoice record with the total commission amount and status 'unpaid'
        $invoice = InvoiceSr::create([
            'role' => 'salesreps',
            'role_user_id' => $salesRepId,
            'month' => $month,
            'total_amount' => $totalCommission,
            'status' => 'unpaid', // Set the initial status to 'unpaid'
            // Add other necessary fields
        ]);

        // Perform any additional actions related to invoice generation
        // For example, sending email notifications, updating other records, etc.

        return $invoice;
    }

    public function Cronhandle()
    {
        // Fetch users with the 'salesreps' role
        $salesReps = User::whereHas('roles', function ($query) {
            $query->where('name', 'salesreps');
        })->get();

        $responses = [];

        foreach ($salesReps as $salesRep) {
            $response = $this->checkPerformance($salesRep->id, now()->subMonth()->format('Y-m'));
            $responses[] = $response->original; // Assuming $response is a response instance
        }

        $salesRepPayoutsList = [];
        // get the invoices
        $invoices = InvoiceSr::where('status', 'unpaid')->get();

        // I need sales rep id, email & invoice amount in the array
        foreach ($invoices as $invoice) {
            $salesRepPayoutsList[] = [

                'sales_rep_id' => $invoice->role_user_id,
                'email' => $invoice->sales_rep->email,
                'amount' => $invoice->total_amount,
            ];
        }

        // make payout
        $this->makePayout($salesRepPayoutsList);

        // update invoice status
        Invoice::where('status', 'unpaid')->update(['status' => 'paid']);

        return response()->json(['responses' => $responses], 200);
    }

    // make payout
    public function makePayout($salesRepPayoutsList)
    {
        // items
        $items = [];
        foreach ($salesRepPayoutsList as $salesRepPayout) {
            $items[] = [
                'recipient_type' => 'EMAIL',
                'amount' => [
                    'value' => $salesRepPayout['amount'],
                    'currency' => 'USD',
                ],
                'receiver' => $salesRepPayout['email'],
                'note' => 'Thank you for your service!',
            ];
        }

        // Replace this with the actual details of your payout
        $payoutData = [
            'sender_batch_header' => [
                'email_subject' => 'You have received a payment from IKY!',
            ],
            'items' => $items,
        ];

        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post("https://api-m.sandbox.paypal.com/v1/payments/payouts", $payoutData, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    // Add any additional headers if needed
                ],
            ]);

        $data = $response->json();

        // Handle the response as needed
        if ($response->successful()) {
            return response()->json($data);
        } else {
            // Handle error response
            return response()->json(['error' => $data], $response->status());
        }
    }

    // paypal access token
    public function getAccessToken()
    {
        $clientId = "AW8ZLUupU0ha_ZDuqN_pb7plUomPNtyHFhIIO2V3C8ulUDaftWvXojRWVFVnyUWRzm92KEAHWSX2eF8D";
        $clientSecret = "EC8r1pkeiwyH8EpFYZFpWJ0JGK2qcder_RJtej2ac0pZtJxAWPqwmVLaD0xVCw31jhws9v56UqqaiZ45";


        // get access token
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://api-m.sandbox.paypal.com/v1/oauth2/token');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
        curl_setopt($curl, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Accept-Language: en_US'
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response)->access_token;
    }


    public function updateSalesRepStatus($salesRepId, $newStatus)
    {
        // Check if the user making the request is an Administrator or Team Manager
        if (auth()->user()->hasRole(['admin', 'manager'])) {
            // Find the sales rep by ID
            $salesRep = User::where('id', $salesRepId)->first();

            if (!$salesRep) {
                return response()->json(['message' => 'Sales rep not found'], 404);
            }

            // Update the sales rep's status
            $salesRep->update(['status' => $newStatus]);

            return response()->json(['message' => 'Sales rep status updated successfully'], 200);
        }

        return response()->json(['message' => 'Permission denied'], 403);
    }

    public function updateAdviceLocalClientStatus($client_id, $user_id, $products)
    {

        // Replace 'YOUR_API_KEY' with your actual API key
        $api_key = env('ADVICE_API_TOKEN');

        // Create a GuzzleHttp client
        $client = new Client();

        // Define the API endpoint
        // $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyclients/' . $client_id;

        // Set request headers
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded', // Use the appropriate content type
            'x-api-token' => $api_key,
        ];

        // Create an order for the client
        $orderUrl = env('ADVICE_API_BASE_URL') . '/' . 'legacyorders';

        // products array 
        $products = json_decode($products, true);
        $products = array_values($products);


        $dataForOrders = [
            'client' => $client_id,
            'products' => $products,
            'card_id' => 'card_1OSJDy4MrsixfUA20088tRSj',
        ];

        $createOrder = $client->post($orderUrl, [
            'headers' => $headers,
            'form_params' => $dataForOrders,
        ]);

        // Check the response status code to handle errors for the client update
        if ($createOrder->getStatusCode() != 200) {
            // Handle the error as needed (e.g., return an error response)
            return response()->json(['error' => 'Failed to create order on advice local'], $createOrder->getStatusCode());
        }

        // Now, update the user's status (assuming a one-to-one relationship)
        $user = User::find($user_id);

        if ($user) {
            $user->update(['status' => 'Active']);
        } else {
            // Handle the case where the user is not found based on the client_id
            return response()->json(['error' => 'User not found'], 404);
        }

        $createdOrder = json_decode($createOrder->getBody()->getContents(), true);

        // Return a success response
        return $createdOrder;
    }

    // create order on advice local
    // public function createAdviceLocalOrder($client_id, $package_id)
    // {
    //     //
    //     // Replace 'YOUR_API_KEY' with your actual API key
    //     $api_key = env('ADVICE_API_TOKEN');

    //     // Create a GuzzleHttp client
    //     $client = new Client();

    //     // Define the API endpoint
    //     $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyorders/' . $client_id;

    //     // Set request headers
    //     $headers = [
    //         'Accept' => 'application/json',
    //         'Content-Type' => 'application/x-www-form-urlencoded', // Use the appropriate content type
    //         'x-api-token' => $api_key,
    //     ];

    //     // Create an associative array of the data to update the client
    //     $data = [
    //         'client' => $client_id,
    //         'products' => [],
    //     ];

    //     // Send a POST request to update the client
    //     $clientResponse = $client->post($url, [
    //         'headers' => $headers,
    //         'form_params' => $data,
    //     ]);

    //     // Check the response status code to handle errors for the client update
    //     if ($clientResponse->getStatusCode() != 200) {
    //         // Handle the error as needed (e.g., return an error response)
    //         return response()->json(['error' => 'Failed to update client'], $clientResponse->getStatusCode());
    //     }
    // }

    public function updateCustomerSubscriptionStatus($subscriptionId, $customerId, Request $request)
    {
        //Paypal Provider
        $provider = new PaypalClient;
        $provider->getAccessToken();


        // Check if the user making the request is an Administrator or Team Manager
        if (auth()->user()->hasRole(['admin', 'manager'])) {
            // Find the sales rep by ID
            $subscribe = Subscription::where('id', $subscriptionId)->where('customer_id', $customerId)->first();

            if (!$subscribe) {
                return response()->json(['message' => 'Subscription not found'], 404);
            }

            $paypalSubscriptionId = $subscribe->paypal_subscription_id;

            $status = $request->input('status');

            // Check if 'status' is provided and not empty
            if ($status !== null && $status !== '') {

                if ($status == "Ended") {

                    // Update the sales rep's status
                    $subscribe->update(['status' => $status]);
                } else if ($status == "Suspended") {
                    // suspend subscription
                    $response = $provider->suspendSubscription($paypalSubscriptionId, 'Suspending the subscription');
                    // Update the sales rep's status
                    $subscribe->update(['status' => $status]);
                    return response()->json([
                        'status' => 'success',
                        'message' => 'suspend subscription success',
                        'data' => $response
                    ]);
                } else if ($status == "Deleted") {
                    // cancel subscription
                    $response = $provider->cancelSubscription($paypalSubscriptionId, 'Deactivating the subscription');
                    // Update the sales rep's status
                    $subscribe->update(['status' => $status]);
                    return response()->json([
                        'status' => 'success',
                        'message' => 'cancel subscription success',
                        'data' => $response
                    ]);
                } else if ($status == "Active") {
                    //ACTIVE
                    $response = $provider->activateSubscription($paypalSubscriptionId, 'Reactivating the subscription');
                    // Update the sales rep's status
                    $subscribe->update(['status' => $status]);
                    return response()->json([
                        'status' => 'success',
                        'message' => 'reactivate subscription success',
                        'data' => $response
                    ]);
                }


                return response()->json(['message' => 'Subscription status updated successfully'], 200);
            } else {
                return response()->json(['error' => 'Invalid or missing status value'], 400);
            }
        }

        return response()->json(['message' => 'Permission denied'], 403);
    }

    // subscribe
    public function store(Request $request)
    {
        // Get the authenticated user (customer)
        $customer = auth()->user();

        // If advice local product selected, create a new client in Advice Local & update the client status
        // $this->updateAdviceLocalClientStatus($customer->customer->client_id, $customer->id);

        $salesRepUsername = $customer->customer->referral_username;
        //Sales reps commissions
        $salesRep = SaleRep::where('username', $salesRepUsername)->first();

        // Assuming you've sent 'selected_package_id' in the request
        $selectedPackageId = $request->input('selected_package_id');

        // Save subscription details in the Subscription table
        $subscription = new Subscription();
        $subscription->customer_id = $customer->id;
        $subscription->package_id = $selectedPackageId;
        $subscription->paypal_subscription_id = $request->input('paypal_subscription_id');
        $subscription->salesrep_commission = $salesRep->commission;
        $subscription->save();

        // Save payment details in the Payment table
        $payment = new Payment();
        $payment->payment_type = '1st Month';
        $payment->transaction_id = $request->input('transaction_id');
        $payment->description = $request->input('description');
        $payment->amount = $request->input('amount') + $request->input('setup_cost');
        $payment->subscription_id = $subscription->id;
        $payment->customer_id = $customer->id;
        $payment->payment_method_id = $request->input('payment_method_id');
        $payment->paypal_subscription_id = $request->input('paypal_subscription_id');
        $payment->save();


        // Create an invoice for the subscription
        $invoice = new Invoice();
        $invoice->role = 'customer'; // Assuming the role is 'customer'
        $invoice->role_user_id = $customer->id;
        $invoice->user_status = "Active";
        $invoice->date = now()->toDateString();
        $invoice->month = now()->format('Y-m');
        $invoice->recurring_amount = $request->input('amount');
        $invoice->setup_fee = $request->input('setup_cost');
        $invoice->total_amount = $request->input('amount') + $request->input('setup_cost');
        $invoice->invoice_type = '1st Invoice'; // You can customize this based on your needs
        $invoice->transaction_id = $payment->id;
        $invoice->paypal_subscription_id = $request->input('paypal_subscription_id');
        $invoice->status = 'paid'; // Set status to unpaid
        $invoice->save();

        // Calculate commissions for the associated sales rep
        // Assuming you have a relation between Customer and SaleRep
        $month = now()->format('Y-m');
        $packagePrice = $request->input('amount') + $request->input('setup_cost'); // Assuming the package price is the payment amount

        // Calculate commissions for the sales rep
        $this->calculateCommissions($salesRepUsername, $month, $packagePrice, $customer->id);

        return response()->json(['message' => 'Subscribe, Payment, and Commissions Calculated Successfully']);
    }
}
