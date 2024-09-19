<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\Subscription;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PackageController extends Controller
{
    public function getAllPackages()
    {
        $packages = Package::all();

        foreach ($packages as $package) {
            $soldCount = Subscription::where('package_id', $package->id)
                // ->where('status', 'active') // You may need to adjust the status condition based on your requirements
                ->count();

            $package->sold_count = $soldCount;
        }

        return json_encode($packages);
    }

    public function getSinglePackage($packageId)
    {
        $package = Package::findOrFail($packageId);

        $soldCount = Subscription::where('package_id', $package->id)
            // ->where('status', 'active') // Adjust based on your requirements
            ->count();

        $package->sold_count = $soldCount;

        return $package;
    }

    public function storePackage(Request $request)
    {

        // $package = Package::create($request->all());
        $package = new Package;
        $package->title = $request->title;
        $package->description = $request->description;
        $package->monthly_price = $request->monthly_price;
        $package->term_months = $request->term_months;
        $package->setup_cost = $request->setup_cost;
        $package->is_advice_local_enabled = $request->is_advice_local_enabled;
        $package->advice_local_products = json_encode($request->advice_local_products);
        $package->status = $request->status;
        $package->save();

        return $package;
    }

    public function updatePackage(Request $request, $packageId)
    {
        $package = Package::findOrFail($packageId);
        $package->title = $request->title;
        $package->description = $request->description;
        $package->monthly_price = $request->monthly_price;
        $package->term_months = $request->term_months;
        $package->setup_cost = $request->setup_cost;
        if ($request->is_advice_local_enabled) {
            $package->is_advice_local_enabled = $request->is_advice_local_enabled;
            $package->advice_local_products = json_encode($request->advice_local_products);
        }
        $package->status = $request->status;

        $package->save();

        return $package;
    }

    public function destroySinglePackage($packageId)
    {
        $package = Package::findOrFail($packageId);
        $package->delete();
        return response()->json(['message' => 'Package deleted'], 200);
    }

    // active package
    public function activatePackage($packageId)
    {

        $package = Package::findOrFail($packageId);

        // pyapal_product_id & paypal_plan_id is not empty
        if (!empty($package->paypal_product_id) && !empty($package->paypal_plan_id)) {
            $package->status = 'Active';
            $package->save();

            return response()->json($package, 200);
        }


        // create paypal product
        $paypal_product = $this->createPaypalProduct($package);

        // create paypal plan
        $paypal_plan = $this->createPaypalPlan($package, $paypal_product);

        // if paypal product & plan are not empty
        if (!empty($paypal_product) && !empty($paypal_plan)) {
            // update package
            $package->status = 'Active';
            $package->paypal_product_id = $paypal_product['id'];
            $package->paypal_plan_id = $paypal_plan['id'];
            $package->save();

            return response()->json($package, 200);
        } else {
            return response()->json(['message' => 'Failed to activate package'], 500);
        }
    }

    // create paypal product
    public function createPaypalProduct($package)
    {
        $provider = new PaypalClient;
        $provider->getAccessToken();
        $description = "Package Description";
        $productdata = [
            "name" => $package->title,
            "description" => $description,
            "type" => "SERVICE",
            "category" => "SOFTWARE",
            "image_url" => "https://example.com/streaming.jpg",
            "home_url" => env("APP_URL", "https://ikylocal.com"),
        ];

        $request_id = 'create-product-' . time();

        $product = $provider->createProduct($productdata, $request_id);

        return $product;
    }

    // create paypal plan
    public function createPaypalPlan($package, $paypal_product)
    {
        $provider = new PaypalClient;
        $provider->getAccessToken();
        $description = "Package Description";

        $planData = [
            "product_id" => $paypal_product['id'],
            "name" => $package->title,
            "description" => $description,
            "status" => "ACTIVE",
            "billing_cycles" => [
                [
                    "frequency" => [
                        "interval_unit" => "MONTH",
                        "interval_count" => 1
                    ],
                    "tenure_type" => "REGULAR",
                    "sequence" => 1,
                    "total_cycles" => $package->term_months,
                    "pricing_scheme" => [
                        "fixed_price" => [
                            "value" => $package->monthly_price,
                            "currency_code" => "USD"
                        ]
                    ]
                ]
            ],
            "payment_preferences" => [
                "auto_bill_outstanding" => true,
                "setup_fee" => [
                    "value" => $package->setup_cost,
                    "currency_code" => "USD"
                ],
                "setup_fee_failure_action" => "CONTINUE",
                "payment_failure_threshold" => 3
            ],
            "taxes" => [
                "percentage" => "0",
                "inclusive" => false
            ]
        ];


        $request_id = 'create-plan-' . time();

        $plan = $provider->createPlan($planData, $request_id);

        return $plan;
    }
}
