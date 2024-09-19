<?php

namespace App\Http\Controllers;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\SaleRep;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\RegistrationEmail;
use App\Models\Commission;
use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;  // For logging errors
use Illuminate\Validation\ValidationException;  // For validation errors
use GuzzleHttp\Exception\ClientException;  // For handling Guzzle client errors
use GuzzleHttp\Exception\RequestException;  // For handling Guzzle request errors
use GuzzleHttp\Exception\ConnectException;


class CustomerController extends Controller
{


    public function getAllCustomers()
    {
        $customers = DB::table('customers')
            ->join('users', 'customers.user_id', '=', 'users.id')
            ->select(
                'customers.id',
                'customers.user_id',
                'customers.client_id',
                'customers.business_name',
                'customers.street',
                'customers.zipCode',
                'customers.country',
                'customers.state',
                'customers.city',
                'customers.referral_username',
                'customers.payment_method',
                'customers.created_at',
                'customers.updated_at',
                'users.name as name',
                'users.email as email',
                'users.phone as phone',
                'users.status as status'
            )
            ->get();

        return $customers;
    }


    public function getCustomeAllCustomers(Request $request)
    {
        $perPage = $request->input('perPage', 25);
        $startDate = $request->input('startDate', '2020-01-01');
        $endDate = $request->input('endDate', Carbon::now()->toDateString());

        // Add 1 day to the endDate
        $endDate = Carbon::parse($endDate)->addDay()->toDateString();



        $customers = DB::table('customers')
            ->join('users', 'customers.user_id', '=', 'users.id')
            ->whereBetween('customers.created_at', [$startDate, $endDate])
            ->select(
                'customers.id',
                'customers.user_id',
                'customers.client_id',
                'customers.business_name',
                'customers.street',
                'customers.zipCode',
                'customers.country',
                'customers.state',
                'customers.city',
                'customers.referral_username',
                'customers.payment_method',
                'customers.created_at',
                'customers.updated_at',
                'users.name as name',
                'users.email as email',
                'users.phone as phone',
                'users.status as status'
            )
            ->orderBy('customers.created_at', 'desc')
            ->paginate($perPage);

        // Loop through each customer and get additional data from getAdviceLocalClient
        foreach ($customers as $customer) {
            $clientDetails = $this->getAdviceLocalClient($customer->client_id);

            // Add the additional data to each customer object
            $customer->client_details = $clientDetails;

            // Get payment information for the customer
            $payments = Payment::where('customer_id', $customer->user_id)->get();

            // Add the payment information to each customer object
            $customer->payments = $payments;

            // Get Invoice information for the customer
            $invoices = Invoice::where('role', 'customer')->where('role_user_id', $customer->user_id)->get();

            // Add the Invoice information to each customer object
            $customer->invoices = $invoices;

            // Get sales representative information based on referral_username
            $salesRep = SaleRep::where('username', $customer->referral_username)
                ->with('user') // Assuming the relationship method in SaleRep model is named 'user'
                ->first();

            // Add the sales representative information to each customer object
            $customer->sales_rep = $salesRep;

            // Get commission information for the sales representative and customer
            $commission = Commission::where([
                'sales_rep_id' => $salesRep->user_id,
                'customer_id' => $customer->user_id,
            ])->get();

            // Add the commission information to each customer object
            $customer->commission = $commission;

            // Get subscription information for the customer
            $subscription = Subscription::where('customer_id', $customer->user_id)->first();

            // Add the subscription information to each customer object
            $customer->subscription = $subscription;

            // Get package title based on the relationship between Subscription and Package
            if ($subscription) {
                $packageTitle = $subscription->package->title;
                // Add the package title to each customer object
                $customer->package_title = $packageTitle;
            }
        }

        return response()->json($customers);
    }




    public function getSingleCustomerData($clientId)
    {
        $customer = DB::table('customers')
            ->join('users', 'customers.user_id', '=', 'users.id')
            ->where('customers.client_id', $clientId)
            ->select(
                'customers.id',
                'customers.user_id',
                'customers.client_id',
                'customers.business_name',
                'customers.street',
                'customers.zipCode',
                'customers.country',
                'customers.state',
                'customers.city',
                'customers.referral_username',
                'customers.payment_method',
                'customers.created_at',
                'customers.updated_at',
                'users.name as name',
                'users.email as email',
                'users.phone as phone',
                'users.status as status'
            )
            ->orderBy('customers.created_at', 'desc')
            ->first();

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        // Get additional data from getAdviceLocalClient
        $clientDetails = $this->getAdviceLocalClient($customer->client_id);

        // Add the additional data to the customer object
        $customer->client_details = $clientDetails;

        // Get payment information for the customer with eager loading of the 'subscription' relationship
        $payments = Payment::with('subscription.package')->where('customer_id', $customer->user_id)->get();


        // Check if any subscriptions were found
        if ($payments->isNotEmpty()) {
            // Add the subscriptions to the customer object as an array
            $customer->payments = $payments->toArray();

            // Iterate through each payment to get package information
            foreach ($customer->payments as &$payment) {
                // Make sure the 'subscription' relationship is loaded
                if (isset($payment['subscription']) && isset($payment['subscription']['package'])) {
                    $packageTitle = $payment['subscription']['package']['title'];
                    $payment['package_title'] = $packageTitle;
                }
            }
        }

        // Get payment information for the customer with eager loading of the 'subscription' and 'invoice' relationships
        $payments = Payment::with(['subscription.package', 'invoice'])
            ->where('customer_id', $customer->user_id)
            ->get();

        // Check if any payments were found
        if ($payments->isNotEmpty()) {
            // Add the payments to the customer object as an array
            $customer->payments = $payments->toArray();

            // Iterate through each payment to get package and invoice information
            foreach ($customer->payments as &$payment) {
                // Make sure the 'subscription' and 'invoice' relationships are loaded
                if (isset($payment['subscription']) && isset($payment['subscription']['package'])) {
                    $packageTitle = $payment['subscription']['package']['title'];
                    $payment['package_title'] = $packageTitle;
                }

                // Check if invoice information is available
                if (isset($payment['invoice'])) {
                    $invoiceId = $payment['invoice']['id'];
                    // You can add more fields from the invoice as needed
                    $payment['invoice_id'] = $invoiceId;
                }
            }
        }




        // Add the payment information to the customer object
        // $customer->payments = $payments;

        // Get Invoice information for the customer
        $invoices = Invoice::where('role', 'customer')->where('role_user_id', $customer->user_id)->get();

        // Add the Invoice information to each customer object
        $customer->invoices = $invoices;

        // Get sales representative information based on referral_username
        $salesRep = SaleRep::where('username', $customer->referral_username)
            ->with('user')
            ->first();

        // Add the sales representative information to the customer object
        $customer->sales_rep = $salesRep;

        // Get commission information for the sales representative and customer
        $commission = Commission::where([
            'sales_rep_id' => $salesRep->user_id,
            'customer_id' => $customer->user_id,
        ])->get();

        // Add the commission information to the customer object
        $customer->commission = $commission;

        // Get subscription information for the customer
        $subscriptions = Subscription::where('customer_id', $customer->user_id)->get();

        // Check if any subscriptions were found
        if ($subscriptions->isNotEmpty()) {
            // Add the subscriptions to the customer object as an array
            $customer->subscriptions = $subscriptions->toArray();

            // Iterate through each subscription to get package information
            foreach ($subscriptions as $subscription) {
                $packageTitle = $subscription->package->title;
                $packageId = $subscription->package->id;

                // Add package information to each subscription in the array
                $subscription->package_title = $packageTitle;
                $subscription->package_id = $packageId;
            }
        }

        return response()->json($customer);
    }


    public function getSingleCustomer($user_id)
    {
        $customer = DB::table('customers')
            ->join('users', 'customers.user_id', '=', 'users.id')
            ->select(
                'customers.id',
                'customers.user_id',
                'customers.client_id',
                'customers.business_name',
                'customers.street',
                'customers.zipCode',
                'customers.country',
                'customers.state',
                'customers.city',
                'customers.referral_username',
                'customers.payment_method',
                'customers.created_at',
                'customers.updated_at',
                'users.name as name',
                'users.email as email',
                'users.phone as phone',
                'users.status as status'

            )
            ->where('customers.user_id', $user_id)
            ->first();

        return $customer;
    }


    public function storeCustomer(Request $request)
    {


        $adviceFormData = [
            'name' => $request->input('businessName'),
            'street' => $request->input('street'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'zipcode' => $request->input('zipCode'),
            'owner' => $request->input('owner'),
            'email' => $request->input('email'),
            'emailPrivate' => $request->input('emailPrivate'),
            'phone' => $request->input('phone'),
            'categoryGoogle' => $request->input('categoryGoogle')
        ];

        // Send data to Advice local
        $adviceClient = $this->sendPostRequest($adviceFormData);
        // Extract 'id' and 'status' from the 'data' key in the response
        $adviceClientData = json_decode($adviceClient->getBody()->getContents(), true);

        $adviceClientId = $adviceClientData['data']['id'];
        $adviceClientStatus = $adviceClientData['data']['status'];

        // Generate a random password
        $randomPassword = Str::random(8);

        // Validate and save the user data
        $user = User::create([
            'name' => $request->input('owner'),
            'email' => $request->input('email'),
            'password' => $randomPassword,
            'phone' => $request->input('phone'),
            'role' => $request->input('role'),
            'status' => $adviceClientStatus,
        ]);

        // Check if a role name is provided in the request
        if ($request->has('role')) {
            $roleName = $request->input('role');

            // Find the role by name
            $findrole = Role::where('name', $roleName)->first();

            // Check if the role exists
            if ($findrole) {
                // Assign the role to the new user
                $user->assignRole($findrole);
            } else {
                // Handle the case where the specified role does not exist
                // You can return an error response or take appropriate action here
            }
        }
        // Load user's roles and permissions
        $user->load('roles.permissions');
        $referralUsername = $request->input('referral_username') ?? "admin";
        $customer = Customer::create([
            'user_id' => $user->id,
            'client_id' => $adviceClientId,
            'business_name' => $request->input('businessName'),
            'payment_method' => $request->input('payment_method'),
            'street' => $request->input('street'), // Replace 'street' with the actual input field name
            'zipCode' => $request->input('zipCode'), // Replace 'zipCode' with the actual input field name
            'country' => $request->input('country'), // Replace 'country' with the actual input field name
            'state' => $request->input('state'), // Replace 'state' with the actual input field name
            'city' => $request->input('city'), // Replace 'city' with the actual input field name
            'referral_username' => $referralUsername, // Replace 'referral_username' with the actual input field name
        ]);


        Mail::to($user->email)->send(new RegistrationEmail($user->email, $randomPassword));


        // Return user details, roles, and permissions in the response
        return response()->json([
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'password' => $randomPassword,
                'phone' => $user->phone,
                'roles' => $user->roles->pluck('name')->implode(', '),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'status' => $adviceClientStatus,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'business_name' => $customer->business_name,
                'street' => $customer->street, // Replace with your actual field name
                'zipCode' => $customer->zipCode, // Replace with your actual field name
                'country' => $customer->country, // Replace with your actual field name
                'state' => $customer->state, // Replace with your actual field name
                'city' => $customer->city, // Replace with your actual field name
                'referral_username' => $customer->referral_username, // Replace with your actual field name
                'payment_method' => $customer->payment_method,
                'client_id' => $adviceClientId,
            ],
            'message' => 'Customer Create Successfull and a mail already send to customer email',
        ], 201);
    }

    //Advice API send data as create
    private function sendPostRequest($adviceFormData)
    {
        $client = new Client();

        $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyclients';
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'x-api-token' => env('ADVICE_API_TOKEN'),
        ];

        $response = $client->post($url, [
            'headers' => $headers,
            'json' => $adviceFormData,
        ]);

        return $response;
    }


    //register Customer without AUTH
    public function registerCustomer(Request $request)
    {
        $adviceFormData = [
            'name' => $request->input('businessName'),
            'street' => $request->input('street'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'zipcode' => $request->input('zipCode'),
            'owner' => $request->input('owner'),
            'email' => $request->input('email'),
            'emailPrivate' => $request->input('emailPrivate'),
            'phone' => $request->input('phone'),
            'categoryGoogle' => $request->input('categoryGoogle')
        ];

        // Send data to Advice local
        $adviceClient = $this->sendPostRequest($adviceFormData);
        // Extract 'id' and 'status' from the 'data' key in the response
        $adviceClientData = json_decode($adviceClient->getBody()->getContents(), true);

        $adviceClientId = $adviceClientData['data']['id'];
        $adviceClientStatus = $adviceClientData['data']['status'];

        // Generate a random password
        $randomPassword = Str::random(8);

        // Validate and save the user data
        $user = User::create([
            'name' => $request->input('owner'),
            'email' => $request->input('email'),
            'password' => $randomPassword,
            'phone' => $request->input('phone'),
            'role' => $request->input('role'),
            'status' => $adviceClientStatus,
        ]);

        // Check if a role name is provided in the request
        if ($request->has('role')) {
            $roleName = $request->input('role');

            // Find the role by name
            $findrole = Role::where('name', $roleName)->first();

            // Check if the role exists
            if ($findrole) {
                // Assign the role to the new user
                $user->assignRole($findrole);
            } else {
                // Handle the case where the specified role does not exist
                // You can return an error response or take appropriate action here
            }
        }
        // Load user's roles and permissions
        $user->load('roles.permissions');

        $referralUsername = $request->input('referral_username') ?? "admin";

        $customer = Customer::create([
            'user_id' => $user->id,
            'client_id' => $adviceClientId,
            'business_name' => $request->input('businessName'),
            'payment_method' => $request->input('payment_method'),
            'street' => $request->input('street'), // Replace 'street' with the actual input field name
            'zipCode' => $request->input('zipCode'), // Replace 'zipCode' with the actual input field name
            'country' => $request->input('country'), // Replace 'country' with the actual input field name
            'state' => $request->input('state'), // Replace 'state' with the actual input field name
            'city' => $request->input('city'), // Replace 'city' with the actual input field name
            'referral_username' => $referralUsername,
        ]);



        Mail::to($user->email)->send(new RegistrationEmail($user->email, $randomPassword));


        // Return user details, roles, and permissions in the response
        return response()->json([
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'password' => $randomPassword,
                'phone' => $user->phone,
                'roles' => $user->roles->pluck('name')->implode(', '),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'status' => $adviceClientStatus,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'business_name' => $customer->business_name,
                'street' => $customer->street, // Replace with your actual field name
                'zipCode' => $customer->zipCode, // Replace with your actual field name
                'country' => $customer->country, // Replace with your actual field name
                'state' => $customer->state, // Replace with your actual field name
                'city' => $customer->city, // Replace with your actual field name
                'referral_username' => $customer->referral_username, // Replace with your actual field name
                'payment_method' => $customer->payment_method,
                'client_id' => $adviceClientId,
            ],
            'message' => 'Thank you for register!! Please check your email for more information and welcome to our platform!',
        ], 201);
    }


    public function getAdviceLocalClient($client_id)
    {
        // Replace 'YOUR_API_KEY' with your actual API key
        $api_key = env('ADVICE_API_TOKEN');

        // Create a GuzzleHttp client
        $client = new Client();

        // Define the API endpoint
        $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyclients/' . $client_id;

        // Set request headers
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'x-api-token' => $api_key,
        ];

        // Send a GET request to the API endpoint
        $response = $client->get($url, ['headers' => $headers]);

        // Check the response status code to handle errors
        if ($response->getStatusCode() == 200) {
            // Successful response, extract and return the 'data' portion
            $responseData = json_decode($response->getBody())->data;
            return response()->json($responseData);
        } else {
            // Handle the error as needed (e.g., return an error response)
            return response()->json(['error' => 'Failed to retrieve client details'], $response->getStatusCode());
        }
    }

    public function updateAdviceLocalClient(Request $request, $client_id)
    {
        // Replace 'YOUR_API_KEY' with your actual API key
        $api_key = env('ADVICE_API_TOKEN');

        // Create a GuzzleHttp client
        $client = new Client();

        // Define the API endpoint
        $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyclients/' . $client_id;

        // Set request headers
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded', // Use the appropriate content type
            'x-api-token' => $api_key,
        ];


        // Create an associative array of the data to update
        $dataToUpdate = $request->all();

        // Send a POST request to the API endpoint
        $response = $client->post($url, [
            'headers' => $headers,
            'form_params' => $dataToUpdate,
        ]);

        // Check the response status code to handle errors
        if ($response->getStatusCode() == 200) {
            // Successful update, return a success response
            return response()->json(['message' => 'Client updated successfully']);
        } else {
            // Handle the error as needed (e.g., return an error response)
            return response()->json(['error' => 'Failed to update client'], $response->getStatusCode());
        }
    }

    //Advice Local Client raw json data Update
    public function updateAdviceLocalClientRawJson(Request $request, $client_id)
    {
        // Replace 'YOUR_API_KEY' with your actual API key
        $api_key = env('ADVICE_API_TOKEN');

        // Create a GuzzleHttp client
        $client = new Client();

        // Define the API endpoint
        $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyclients/' . $client_id;

        // Set request headers
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json', // Use JSON content type
            'x-api-token' => $api_key,
        ];

        // $dataToUpdate = [
        //     'hoursObject' => [
        //         'periods' => [
        //             [
        //                 'openDay' => 'SUNDAY',
        //                 'openTime' => '00:00',
        //                 'closeDay' => 'SUNDAY',
        //                 'closeTime' => '23:59',
        //             ],
        //             // Add other periods as needed
        //         ],
        //     ],
        //     // Add other fields as needed
        // ];
        // Retrieve raw JSON data from the request body
        $jsonData = $request->json()->all();


        // Send a POST request to the API endpoint
        $response = $client->post($url, [
            'headers' => $headers,
            'json' => $jsonData, // Use json instead of form_params
        ]);

        // Check the response status code to handle errors
        if ($response->getStatusCode() == 200) {
            // Successful update, return a success response
            return response()->json(['message' => 'Client data updated successfully']);
        } else {
            // Handle the error as needed (e.g., return an error response)
            return response()->json(['error' => 'Failed to update client'], $response->getStatusCode());
        }
    }

    //Advice Local Client Gellary Image Upload
    public function uploadAdviceLocalClientImage(Request $request, $client_id)
    {
        // Replace 'YOUR_API_KEY' with your actual API key
        $api_key = env('ADVICE_API_TOKEN');

        // Create a GuzzleHttp client
        $client = new Client();

        // Define the API endpoint
        $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyclientimages/' . $client_id;

        // Set request headers
        $headers = [
            'x-api-token' => $api_key,
        ];

        // Create a FormData object for multipart/form-data
        $formData = [];

        // Add the image file to the FormData object
        $formData[] = [
            'name' => 'image',
            'contents' => fopen($request->file('image')->path(), 'r'),
            'filename' => $request->file('image')->getClientOriginalName(),
        ];

        // Send a POST request to the API endpoint
        $response = $client->request('POST', $url, [
            'headers' => $headers,
            'multipart' => $formData,
        ]);

        // Check the response status code to handle errors
        if ($response->getStatusCode() == 200) {
            // Successful image upload, return a success response
            return response()->json(json_decode($response->getBody(), true));
        } else {
            // Handle the error as needed (e.g., return an error response)
            return response()->json(['error' => 'Failed to upload image'], $response->getStatusCode());
        }
    }

    //Advice Local Client Tag Image Upload
    public function uploadAdviceLocalClientImageWithTag(Request $request, $client_id, $tag)
    {
        // Replace 'YOUR_API_KEY' with your actual API key
        $api_key = env('ADVICE_API_TOKEN');

        // Create a GuzzleHttp client
        $client = new Client();

        // Define the API endpoint with the specified tag
        $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyclientimages/' . $client_id . '/' . $tag;

        // Set request headers
        $headers = [
            'x-api-token' => $api_key,
        ];

        // Create a FormData object for multipart/form-data
        $formData = [];

        // Add the image file to the FormData object
        $formData[] = [
            'name' => 'image',
            'contents' => fopen($request->file('image')->path(), 'r'),
            'filename' => $request->file('image')->getClientOriginalName(),
        ];

        // Send a POST request to the API endpoint
        $response = $client->request('POST', $url, [
            'headers' => $headers,
            'multipart' => $formData,
        ]);

        // Check the response status code to handle errors
        if ($response->getStatusCode() == 200) {
            // Successful image upload, return a success response
            return response()->json(json_decode($response->getBody(), true));
        } else {
            // Handle the error as needed (e.g., return an error response)
            return response()->json(['error' => 'Failed to upload image with tag'], $response->getStatusCode());
        }
    }
    //Advice Local Client All Image get
    public function getAdviceLocalClientAllImages($client_id)
    {
        // Replace 'YOUR_API_KEY' with your actual API key
        $api_key = env('ADVICE_API_TOKEN');

        // Create a GuzzleHttp client
        $client = new Client();

        // Define the API endpoint
        $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyclientimages/' . $client_id;

        // Set request headers
        $headers = [
            'x-api-token' => $api_key,
        ];

        // Send a GET request to the API endpoint
        $response = $client->request('GET', $url, [
            'headers' => $headers,
        ]);

        // Check the response status code to handle errors
        if ($response->getStatusCode() == 200) {
            // Successful response, return the data
            return response()->json(json_decode($response->getBody(), true));
        } else {
            // Handle the error as needed (e.g., return an error response)
            return response()->json(['error' => 'Failed to get client images'], $response->getStatusCode());
        }
    }

    //Advice Local Client a Image Delete
    public function deleteAdviceLocalClientImage($client_id, $image_name)
    {
        // Replace 'YOUR_API_KEY' with your actual API key
        $api_key = env('ADVICE_API_TOKEN');

        // Create a GuzzleHttp client
        $client = new Client();

        // Define the API endpoint
        $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyclientimages/' . $client_id . '/' . $image_name;

        // Set request headers
        $headers = [
            'x-api-token' => $api_key,
        ];

        // Send a DELETE request to the API endpoint
        $response = $client->request('DELETE', $url, [
            'headers' => $headers,
        ]);

        // Check the response status code to handle errors
        if ($response->getStatusCode() == 200) {
            // Successful response, return the data
            return response()->json(json_decode($response->getBody(), true));
        } else {
            // Handle the error as needed (e.g., return an error response)
            return response()->json(['error' => 'Failed to delete client image'], $response->getStatusCode());
        }
    }
    //Advice Local Client get reports data
    public function getAdviceLocalClientReport($client_id)
    {
        // Replace 'YOUR_API_KEY' with your actual API key
        $api_key = env('ADVICE_API_TOKEN');

        // Create a GuzzleHttp client
        $client = new Client();

        // Define the API endpoint for client report
        $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyclients/' . $client_id . '/report';

        // Set request headers
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'x-api-token' => $api_key,
        ];

        // Send a GET request to the API endpoint for client report
        $response = $client->get($url, ['headers' => $headers]);

        // Check the response status code to handle errors
        if ($response->getStatusCode() == 200) {
            // Successful response, extract and return the 'data' portion
            $responseData = json_decode($response->getBody())->data;
            return response()->json($responseData);
        } else {
            // Handle the error as needed (e.g., return an error response)
            return response()->json(['error' => 'Failed to retrieve client report'], $response->getStatusCode());
        }
    }







    public function updateCustomer(Request $request, $user_id)
    {
        // Find the user and customer records by user_id
        $user = User::find($user_id);
        $customer = Customer::where('user_id', $user_id)->first();

        if (!$user || !$customer) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Update user data (skip password update if it's null)
        $userData = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'status' => $request->input('status'),
        ];

        if ($request->has('password')) {
            $userData['password'] = $request->input('password');
        }

        $user->update($userData);

        // Check if a role name is provided in the request
        if ($request->has('role')) {
            $roleName = $request->input('role');

            // Find the role by name
            $findrole = Role::where('name', $roleName)->first();

            // Check if the role exists
            if ($findrole) {
                // Sync the user's roles
                $user->syncRoles([$findrole->id]);
            } else {
                // Handle the case where the specified role does not exist
                // You can return an error response or take appropriate action here
            }
        }

        // Update customer data
        $customer->update([
            'payment_method' => $request->input('payment_method'),
            'street' => $request->input('street'),
            'zipCode' => $request->input('zipCode'),
            'country' => $request->input('country'),
            'state' => $request->input('state'),
            'city' => $request->input('city'),
            'referral_username' => $request->input('referral_username'),
        ]);

        // Load user's roles and permissions
        $user->load('roles.permissions');

        return response()->json([
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->roles->pluck('name')->implode(', '),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'street' => $customer->street,
                'zipCode' => $customer->zipCode,
                'country' => $customer->country,
                'state' => $customer->state,
                'city' => $customer->city,
                'referral_username' => $customer->referral_username,
                'payment_method' => $customer->payment_method,
            ],
            'message' => 'Customer Updated Successfully',
        ], 200);
    }

    // add or change sale rep
    public function addOrChangeSaleRep(Request $request, $customer_id)
    {
        $customer = Customer::find($customer_id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $oldSalesRep = SaleRep::where('username', $customer->referral_username)->first();

        $commission = Commission::where([
            'sales_rep_id' => $oldSalesRep->user_id,
            'customer_id' => $customer->user_id,
            'commission_type' => 'subscription',
        ])->first();

        $newSalesRep = SaleRep::where('username', $request->input('saleRepUsername'))->first();

        if (!$newSalesRep) {
            return response()->json(['message' => 'Sales representative not found'], 404);
        }

        $customer->update(['referral_username' => $request->input('saleRepUsername')]);

        // update commission sales rep
        if ($commission) {
            $commission->update([
                'sales_rep_id' => $newSalesRep->user_id,
            ]);
        }

        return response()->json(['message' => 'Sales representative updated successfully'], 200);
    }

    public function destroySingleCustomer($client_id)
    {
        $customer = Customer::where('client_id', $client_id)->first();
        $user = User::find($customer->user_id);
        $user->delete();
        $customer->delete();

        return response()->json(['message' => 'Customer deleted'], 200);
    }

    public function updateCustomerStatus($customer_id, Request $request)
    {
        $status = $request->input('status');

        // Check if 'status' is provided and not empty
        if ($status !== null && $status !== '') {
            User::find($customer_id)->update(['status' => $status]);

            return response()->json(['message' => 'Customer status successfully changed'], 200);
        } else {
            return response()->json(['error' => 'Invalid or missing status value'], 400);
        }
    }


    public function updateAdviceLocalClientStatus(Request $request, $client_id)
    {
        // Replace 'YOUR_API_KEY' with your actual API key
        $api_key = env('ADVICE_API_TOKEN');

        // Create a GuzzleHttp client
        $client = new Client();

        // Define the API endpoint
        $url = env('ADVICE_API_BASE_URL') . '/' . 'legacyclients/' . $client_id;

        // Set request headers
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'x-api-token' => $api_key,
        ];


        // Create form_params with the same structure as the cURL request
        $dataToUpdate = [
            'status' => $request->input('status'),
        ];

        // Send a POST request to the API endpoint
        $response = $client->post($url, [
            'headers' => $headers,
            'form_params' => $dataToUpdate,
        ]);


        // Check the response status code to handle errors
        if ($response->getStatusCode() == 200) {
            // Successful update, return a success response
            return response()->json(['message' => 'Client status updated successfully']);
        } else {
            // Handle the error as needed (e.g., return an error response)
            return response()->json(['error' => 'Failed to update client status'], $response->getStatusCode());
        }
    }

    public function deleteAdviceLocalOrder($subscription_id)
    {
        $subscription = Subscription::find($subscription_id);

        if (!$subscription) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        // advice local order delete
        $api_key = env('ADVICE_API_TOKEN');
        $client = new Client();
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded', // Use the appropriate content type
            'x-api-token' => $api_key,
        ];

        $orderUrl = env('ADVICE_API_BASE_URL') . '/' . 'legacyorders/' . $subscription->advice_local_order_id;

        $deleteOrder = $client->delete($orderUrl, [
            'headers' => $headers,
        ]);

        if ($deleteOrder->getStatusCode() == 200) {
            // Successful order delete, return a success response
            $subscription->update([
                'status' => 'Suspended',
            ]);
            return response()->json(['message' => 'Order deleted successfully'], 200);
        } else {
            // Handle the error as needed (e.g., return an error response)
            return response()->json(['error' => 'Failed to delete order'], $deleteOrder->getStatusCode());
        }
    }
    public function checkAndFetchAdviceLocalReport($customerId)
    {
        // Retrieve the customer record
        $customer = Customer::find($customerId);
    
        // Check if the customer exists, has a client_id for Advice Local, and has an active subscription
        $hasActiveSubscription = DB::table('subscriptions as s')
            ->join('packages as pkg', 's.package_id', '=', 'pkg.id')
            ->where('s.customer_id', $customer->user_id)
            ->where('pkg.name', 'Advice Local')
            ->where('s.status', 'Active')
            ->exists();
    
        // If any of the conditions fail, return an error response
        if (!$customer || !$customer->client_id || !$hasActiveSubscription) {
            return response()->json(['error' => 'Inactive subscription or client ID missing'], 400);
        }
    
        // If the subscription is active and client ID exists, fetch and update the latest report
        return $this->getAdviceLocalClientReport($customer->client_id);
    }

}