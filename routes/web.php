<?php

use App\Http\Controllers\PaypalController;
use App\Models\Commission;
use App\Models\Payout;
use App\Models\Setting;

use App\Models\PerformanceNumber;
use App\Models\SaleRep;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use App\Http\Controllers\WebhookController;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\WebhookClient\Http\Controllers\WebhookController as SpatieWebhookController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


Route::get('/', function () {

    // IKY api is running message
    return response()->json(['message' => 'IKY API is running'], 200);
});

// paypal webhook
Route::webhooks('/webhooks/paypal', 'paypal-webhook');


 Route::get('/testonborad', function () {
    $salesRepcreated_at = "2024-01-27 07:27:10";     $salesrepsregistrationMonth = date('n', strtotime($salesRepcreated_at));

     // Retrieve the setting value as a single instance
     $default_onboarding_period = Setting::where('key', 'default_onboarding_period')->first()->value;

     // Ensure $default_onboarding_period is not null before performing operations
     if ($default_onboarding_period !== null) {
         $sum = $salesrepsregistrationMonth + $default_onboarding_period + 1;

         // Format the date
         $formattedDate = date("Y-m-01", strtotime("2024-$sum-01"));
     } else {
          //Handle case where the setting value is not found
         return "Default onboarding period not found.";
     }

     // Get current date
     $current_date = date("Y-m-d");

      //Compare current date with the formatted date
     if ($current_date <= $formattedDate) {
         return "The Performance Number is suspended until " . $formattedDate . '-' . $current_date;
     } else {
         return "The Performance Number is active" . $formattedDate . '-' . $current_date;
     }
 });
