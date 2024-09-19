<?php

namespace App\Jobs;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchAdviceLocalReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $customerId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $customer = Customer::find($this->customerId);
        if ($customer && $customer->client_id) {
            try {
                $response = Http::get(route('admin.check-and-fetch-advice-local-report', ['client_id' => $customer->client_id]));
                // Optionally log the response
                Log::info('Advice Local report fetch response:', ['response' => $response->json()]);
            } catch (\Exception $e) {
                Log::error('Error fetching Advice Local report: ' . $e->getMessage());
            }
        }
    }
}
