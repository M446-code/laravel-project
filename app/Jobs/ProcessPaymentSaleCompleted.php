<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SaleRep;
use App\Models\Commission;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Models\WebhookCall;

class ProcessPaymentSaleCompleted implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected WebhookCall $webhookCall)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = $this->webhookCall->payload['resource'];
        // get the event type from the payload
        $eventType = $this->webhookCall->payload['event_type'];

        // check if invoice exists
        $invoice = Invoice::where('paypal_subscription_id', $message['billing_agreement_id'])
            ->where('month', Carbon::now()->format('Y-m'))
            ->first();

        // get user_id from subscription table
        $subscription = \App\Models\Subscription::where('paypal_subscription_id', $message['billing_agreement_id'])->first();

        // if invoice exists
        if ($invoice) {
            // if event type is PAYMENT.SALE.COMPLETED
            if ($eventType === 'PAYMENT.SALE.COMPLETED') {

                // update payment status to Success
                $payment = \App\Models\Payment::find($invoice->transaction_id);
                $payment->update([
                    'transaction_id' => $message['id'],
                    'result_type' => 'Success',
                ]);

                // update invoice status to paid
                $invoice->update([
                    'status' => 'paid'
                ]);

                // Update the subscription with the new failed payments count
                $subscription->update([
                    'failed_payments_count' => 0,
                    'status' => 'Active',
                ]);
            }
        } else {
            // if invoice does not exist
            // if event type is PAYMENT.SALE.COMPLETED
            if ($eventType === 'PAYMENT.SALE.COMPLETED') {

                $subscribe_customer = \App\Models\User::where('id', $subscription->customer_id)->first();
                // create payment
                $payment = \App\Models\Payment::create([
                    'description' => 'Paypal Subscription',
                    'amount' => $message['amount']['total'],
                    'payment_type' => 'recurring',
                    'subscription_id' => $subscription->id,
                    'payment_method_id' => 1, // 'Paypal'
                    'paypal_subscription_id' => $message['billing_agreement_id'],
                    'customer_id' => $subscription->customer_id,
                    'transaction_id' => $message['id'],
                    'result_type' => 'Success',
                ]);

                //will be add here salesreps commission.

                // create invoice
                Invoice::create([
                    'role' => 'customer',
                    'role_user_id' => $subscription->customer_id,
                    'user_status' => $subscribe_customer->status,
                    'date' => Carbon::now()->format('Y-m-d'),
                    'month' => Carbon::now()->format('Y-m'),
                    'recurring_amount' => $message['amount']['total'],
                    'setup_fee' => 0,
                    'total_amount' => $message['amount']['total'],
                    'invoice_type' => 'recurring',
                    'transaction_id' => $payment->id,
                    'paypal_subscription_id' => $message['billing_agreement_id'],
                    'status' => 'paid',
                ]);



                // public function calculateCommissions($salesRepUsername, $month, $packagePrice, $customerId)
                $Customer = Customer::where('user_id', $subscription->customer_id)->first();




                // Find the sales rep by username
                $salesRep = SaleRep::where('username', $Customer->referral_username)->first();


                // Calculate the commission as 10% of the package price
                $commissionAmount = $message['amount']['total'] * ($salesRep->commission / 100); // 10% commission

                // Create a new commission record in the Commissions table
                $commission = new Commission([
                    'sales_rep_id' => $salesRep->user_id,
                    'month' => Carbon::now()->format('Y-m'),
                    'commission_type' => 'recurring',
                    'commission_amount' => $commissionAmount,
                    'paid' => false, // Assuming commissions are initially unpaid
                    'customer_id' => $subscription->customer_id,
                ]);
                $commission->save();
            }
        }
    }
}
