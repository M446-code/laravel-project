<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Srmklive\PayPal\Services\PayPal as PayPalClient;


class PaypalController extends Controller
{
    private $provider;

    // constructor paypal provider
    public function __construct()
    {
        $this->provider = new PaypalClient;
        $this->provider->getAccessToken();
    }

    // get access token
    public function getAccessToken()
    {
        // get access token
        
        return response()->json([
            'status' => 'success',
            'message' => 'get access token success',
            'data' => $this->provider->getAccessToken()
        ]);
    }

    // show subscription details
    public function showPaypalSubscriptionDetails($paypalSubscriptionId)
    {
        // get access token
        $this->provider->getAccessToken();
        
        // show subscription details
        $response = $this->provider->showSubscriptionDetails($paypalSubscriptionId);

        return response()->json([
            'status' => 'success',
            'message' => 'show subscription details success',
            'data' => $response
        ]);
    }

    // cancel subscription
    public function cancelSubscription($paypalSubscriptionId)
    {
        // get access token
        $this->provider->getAccessToken();
        
        // cancel subscription
        $response = $$this->provider->cancelSubscription($paypalSubscriptionId, 'Deactivating the subscription');

        return response()->json([
            'status' => 'success',
            'message' => 'cancel subscription success',
            'data' => $response
        ]);
    }

    // reactivate subscription
    public function reactivateSubscription($paypalSubscriptionId)
    {
        // get access token
        $this->provider->getAccessToken();
        
        // reactivate subscription
        $response = $this->provider->activateSubscription($paypalSubscriptionId, 'Reactivating the subscription');

        return response()->json([
            'status' => 'success',
            'message' => 'reactivate subscription success',
            'data' => $response
        ]);
    }

    // suspend subscription
    public function suspendSubscription($paypalSubscriptionId)
    {
        // get access token
        $this->provider->getAccessToken();
        
        // suspend subscription
        $response = $this->provider->suspendSubscription($paypalSubscriptionId, 'Suspending the subscription');

        return response()->json([
            'status' => 'success',
            'message' => 'suspend subscription success',
            'data' => $response
        ]);
    }
}
