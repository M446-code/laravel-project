<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\InvoiceSr;
use Illuminate\Support\Facades\Log;
use App\Models\Payout;
use App\Models\SaleRep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\WebhookClient\Models\WebhookCall;

class ProcessPaymentPayoutsItemUnclaimed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected WebhookCall $webhookCall;

    public function __construct(WebhookCall $webhookCall)
    {
        $this->webhookCall = $webhookCall;
    }

    public function handle()
    {
        $logResponses = [];

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

        // $saleRep = SaleRep::where('paypal_account', $payoutReceiverEmail)->first();

        // if (!$saleRep) {
        //     Log::error('No SaleRep record found for payout receiver email: ' . $payoutReceiverEmail);
        //     return;
        // }

        // $payoutItem = Payout::where('role_user_id', $saleRep->user_id)
        //     ->where('paypal_transaction_id', $payoutBatchId)
        //     ->first();

        $payoutItem = Payout::where('payout_item_id', $resource['payout_item_id'])
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

        $logResponses[] = $payoutItem->toArray();

        // $invoice = InvoiceSr::where('role_user_id', $saleRep->user_id)
        //     ->where('transaction_id', $payoutBatchId)
        //     ->first();

        // if (!$invoice) {
        //     Log::error('No Invoice record found for SaleRep with email: ' . $saleRep->email);
        //     return;
        // }

        $payoutItem->invoice->update([
            'transaction_id' => $transactionId,
            'status' => 'unpaid',
        ]);

        $logResponses[] = $payoutItem->invoice->toArray();

        // Log the responses
        Log::info('Unclaimed payout item processed:', $logResponses);
    }
}
