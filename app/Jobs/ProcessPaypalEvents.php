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

class ProcessPaypalEvents implements ShouldQueue
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
            
        // todo do something with $message['id']    
        Log::info('Paypal webhook received: ' . $message['id']);

        // get the event type from the payload
        $eventType = $this->webhookCall->payload['event_type'];
        
        // get this month invoice by paypal_subscription_id & date
        $invoice = Invoice::where('paypal_subscription_id', $message['id'])
            ->where('date', Carbon::now()->format('Y-m-d'))
            ->first();

        // if invoice exists
        if ($invoice) {
            // if event type is PAYMENT.SALE.COMPLETED
            if ($eventType === 'PAYMENT.SALE.COMPLETED') {
                // update invoice status to paid
                $invoice->update([
                    'status' => 'paid',
                ]);
            }
            // if event type is PAYMENT.SALE.DENIED
            if ($eventType === 'PAYMENT.SALE.DENIED') {
                // update invoice status to denied
                $invoice->update([
                    'status' => 'denied',
                ]);
            }
        }
    }
}
