<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Models\InvoiceSr;
use App\Models\SaleRep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Models\WebhookCall;

use Srmklive\PayPal\Services\PayPal as PayPalClient;

class ProcessPaymentPayoutsBatchSuccess implements ShouldQueue
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
        // Initialize an empty array to store responses
        $logResponses = [];

        // get payout resource from the payload
        $resource = $this->webhookCall->payload['resource'];
        $payoutBatchId = $resource['batch_header']['payout_batch_id'];

        // get the payout batch details
        $provider = new PayPalClient;
        $provider->getAccessToken();
        $payoutDetails = $provider->showBatchPayoutDetails($payoutBatchId);

        // process the payout details
        foreach ($payoutDetails['items'] as $item) {
            // $saleReps = SaleRep::where('paypal_account', $item['payout_item']['receiver'])->get();    

            // if ($saleReps->isNotEmpty()) {
            //     foreach ($saleReps as $saleRep) {
            //         $payoutItem = Payout::where('role_user_id', $saleRep->user_id)
            //             ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = '" .now()->subMonth()->format('Y-m') . "'")
            //             ->first();

            //         if ($payoutItem) {
            //             $payoutItem->update([
            //                 'paypal_transaction_id' => $item['transaction_id'],
            //                 'result_type' => $item['transaction_status'],
            //             ]);
            //             $logResponses[] = $payoutItem->toArray();
            //         } else {
            //             // Handle case when no Payout record is found
            //             $logResponses[] = ['error' => 'No Payout record found'];
            //         }
            //     }
            // } else {
            //     // Handle case when no SaleRep record is found
            //     $logResponses[] = ['error' => 'No SaleRep record found'];
            // }

            $payoutItem = Payout::where('payout_item_id', $item['payout_item_id'])
                ->with('invoice')
                ->first();

            if ($payoutItem) {
                $payoutItem->update([
                    'paypal_transaction_id' => $item['transaction_id'],
                    'result_type' => $item['transaction_status'],
                ]);

                $payoutItem->invoice->update([
                    'transaction_id' => $item['transaction_id'],
                    'status' => 'paid',
                ]);

                $logResponses[] = $payoutItem->toArray();
            } else {
                // Handle case when no Payout record is found
                $logResponses[] = ['error' => 'No Payout record found'];
            }
        }

        // Log the responses
        Log::info('Payout processing responses:', $logResponses);
    }
}
