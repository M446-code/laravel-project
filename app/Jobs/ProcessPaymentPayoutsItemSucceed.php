<?php

namespace App\Jobs;

use App\Models\Commission;
use App\Models\Invoice;
use App\Models\InvoiceSr;
use App\Models\Payout;
use App\Models\SaleRep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Models\WebhookCall;

class ProcessPaymentPayoutsItemSucceed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected WebhookCall $webhookCall;

    /**
     * Create a new job instance.
     */
    public function __construct(WebhookCall $webhookCall)
    {
        $this->webhookCall = $webhookCall;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payload = $this->webhookCall->payload;

        if (!isset($payload['resource'])) {
            Log::error('Invalid webhook payload: Resource data missing');
            return;
        }

        $resource = $payload['resource'];

        if (!isset($resource['payout_item']['receiver'], $resource['transaction_id'], $resource['transaction_status'], $resource['payout_batch_id'])) {
            Log::error('Invalid webhook payload: Required fields missing');
            return;
        }

        $payoutReceiverEmail = $resource['payout_item']['receiver'];
        $transactionId = $resource['transaction_id'];
        $transactionStatus = $resource['transaction_status'];
        $payoutBatchId = $resource['payout_batch_id'];
        $payoutItemId = $resource['payout_item_id'];

        // $saleRep = SaleRep::where('paypal_account', $payoutReceiverEmail)->first();

        // if (!$saleRep) {
        //     Log::error('No SaleRep record found for payout receiver email: ' . $payoutReceiverEmail);
        //     return;
        // }

        // $payoutItem = Payout::where('role_user_id', $saleRep->user_id)
        //     ->where('paypal_transaction_id', $payoutBatchId)
        //     ->first();

        $payoutItem = Payout::where('payout_item_id', $payoutItemId)
            ->with('invoice')
            ->first();

        if (!$payoutItem) {
            Log::error('No Payout record found');
            return;
        }

        $payoutItem->update([
            'paypal_transaction_id' => $transactionId,
            'result_type' => $transactionStatus,
        ]);

        $invoice = $payoutItem->invoice;

        if (!$invoice) {
            Log::error('No Invoice record found');
            return;
        }

        $invoice->update([
            'transaction_id' => $transactionId,
            'status' => 'paid',
        ]);

        // insert into commission table
        $earning = new Commission();
        $earning->sales_rep_id = $payoutItem->role_user_id;
        $earning->month = $invoice->month;
        $earning->description = 'Invoice #' . $invoice->id . ' payment related to ' . $invoice->month . ' Earnings';
        $earning->commission_type = 'deduction';
        $earning->commission_amount = 0;
        $earning->deduction = $invoice->total_amount;
        $earning->paid = true;
        $earning->save();
    }
}
