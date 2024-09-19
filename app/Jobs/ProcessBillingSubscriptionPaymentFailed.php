<?php

namespace App\Jobs;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Models\WebhookCall;

class ProcessBillingSubscriptionPaymentFailed implements ShouldQueue
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
        $failedPaymentsCount = $subscription->failed_payments_count ?? 0;
        // if invoice exists
        if ($invoice) {            
            // if event type is PAYMENT.SALE.DENIED
            if ($eventType === 'BILLING.SUBSCRIPTION.PAYMENT.FAILED') {
                // update invoice status to denied
                // Increment the failed payments count
                $failedPaymentsCount++;

                $invoice->update([
                    'status' => 'failed',
                ]);
                // update payment status to Failed
                $payment = \App\Models\Payment::find($invoice->transaction_id);
                $payment->update([
                    'result_type' => 'Failed',
                ]);

                // Update the subscription with the new failed payments count
                $subscription->update([
                    'failed_payments_count' => $failedPaymentsCount,
                ]);

                // Check if the subscription has reached 3 consecutive failed payments
                if ($failedPaymentsCount >= 3) {
                   // Update the subscription with the new failed payments count
                    $subscription->update([
                        'status' => 'Suspended',
                    ]);

                }
            }
        } else {            
            // if event type is PAYMENT.SALE.DENIED
            if ($eventType === 'BILLING.SUBSCRIPTION.PAYMENT.FAILED') {
                $subscribe_customer = \App\Models\User::where('id', $subscription->user_id)->first();
                // create payment
                $payment = \App\Models\Payment::create([
                    'description' => 'Paypal Subscription',
                    'amount' => $message['amount']['total'],
                    'payment_type' => 'recurring',
                    'subscription_id' => $subscription->id,
                    'payment_method_id' => 1, // 'Paypal'
                    'paypal_subscription_id' => $message['billing_agreement_id'],
                    'customer_id' => $subscription->user_id,
                    'transaction_id' => $message['id'],
                    'result_type' => 'Failed',
                ]);
                // create invoice
                Invoice::create([
                    'role' => 'customer',
                    'role_user_id' => $subscription->user_id,
                    'user_status' => $subscribe_customer->status,
                    'date' => Carbon::now()->format('Y-m-d'),
                    'month' => Carbon::now()->format('Y-m'),
                    'total_amount' => $message['amount']['total'],
                    'invoice_type' => 'recurring',
                    'transaction_id' => $payment->id,
                    'paypal_subscription_id' => $message['billing_agreement_id'],
                    'status' => 'failed',
                ]);

            }
        }
        
    }


    

}
