<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SaleRepController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\FAQController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EarningController;
use App\Http\Controllers\InvoiceController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

use App\Models\PerformanceNumber;

Route::get('/performance-numbers', function () {
    $performanceNumbers = PerformanceNumber::all();

    return response()->json($performanceNumbers);
});

Route::post('/send-advicelocal-post-request', [CustomerController::class, 'sendPostRequest']);

Route::get('check-sales-reps/{username}', [SaleRepController::class, 'checkSalesRepsUsername']);
Route::get('check-sales-reps-status/{username}', [SaleRepController::class, 'checkSalesRepsUsernameStatus']);

Route::get('check-sales-reps-email-unique/{email}', [SaleRepController::class, 'checkSalesRepsEmail']);
Route::post('register-salesreps', [SaleRepController::class, 'onlinestoreSalesReps']);
Route::post('register-customer', [CustomerController::class, 'registerCustomer']);
Route::get('admin/get-all-packages', [PackageController::class, 'getAllPackages']);
Route::get('admin/get-single-package/{packageId}', [PackageController::class, 'getSinglePackage']);

Route::post('/calculate-commissions', [SubscriptionController::class, 'calculateCommissions']);
Route::post('/check-performance/{salesRepId}/{month}', [SubscriptionController::class, 'checkPerformance']);
Route::post('/track-non-performing-sales-reps', [SubscriptionController::class, 'trackNonPerformingSalesReps']);
Route::post('/mark-commissions-non-payable/{salesRepId}/{month}', [SubscriptionController::class, 'markCommissionsAsNonPayable']);

// Cron Job

Route::get('/handle-cron', [SubscriptionController::class, 'Cronhandle']);

Route::get('/check-performance/{salesRepId}/{month}', [SubscriptionController::class, 'checkPerformance']);

// access token test
Route::get('/paypal-access-token', [SubscriptionController::class, 'getAccessToken']);

// CRUD Admin Panel
Route::middleware(['auth:sanctum'])->group(function () {

    //Dashboard 
    Route::get('/get-all-stats', [DashboardController::class, 'total_stats']);
    Route::get('/admin/all-widget', [AdminController::class, 'adminAllWidget']);
    Route::get('/daily-revenue-graph', [AdminController::class, 'getDailyRevenueData']);
    Route::get('/daily-sales-graph', [AdminController::class, 'getDailySalesRepData']);


    //Role
    Route::post('admin/add-role', [RoleController::class, 'storeRole']);
    Route::put('admin/update-role/{id}', [RoleController::class, 'updateRole']);
    Route::delete('admin/delete-role/{id}', [RoleController::class, 'destroyRole']);
    Route::get('admin/get-all-roles', [RoleController::class, 'getAllRole']);

    //RolePermission
    Route::get('admin/get-all-roles-wise-permissions', [RolePermissionController::class, 'getAllRoleWisePermissions']);
    Route::get('admin/get-single-role-wise-permissions/{roleName}', [RolePermissionController::class, 'getSingleRoleWisePermission']);
    Route::put('admin/update-single-role-wise-permissions/{roleName}', [RolePermissionController::class, 'updateRoleWisePermission']);
    Route::put('admin/update-all-roles-wise-permissions', [RolePermissionController::class, 'updateAllRolesPermissions']);

    //User
    Route::get('/user', [LoginController::class, 'getAuthUserDetails']);
    Route::get('admin/get-single-user/{id}', [AdminController::class, 'getSingleUser']);
    Route::post('admin/add-user', [AdminController::class, 'storeUser']);
    Route::get('admin/get-all-users', [AdminController::class, 'getAllusers']);
    Route::get('admin/get-all-custom-users', [AdminController::class, 'getCustomAllUsers']);
    Route::patch('admin/update-user/{id}', [AdminController::class, 'updateSingleUser']);
    Route::delete('admin/delete-user/{id}', [AdminController::class, 'destroySingleUser']);

    //SaleRep
    Route::get('admin/get-all-sales-reps', [SaleRepController::class, 'getAllSalesReps']);
    Route::get('admin/get-single-sales-reps/{user_id}', [SaleRepController::class, 'getSingleSalesReps']);
    Route::post('admin/add-sales-reps', [SaleRepController::class, 'storeSalesReps']);
    Route::patch('admin/update-sales-reps/{user_id}', [SaleRepController::class, 'updateSalesReps']);
    Route::patch('admin/update-online-sales-reps/{user_id}', [SaleRepController::class, 'updateOnlineStoreSalesReps']);
    Route::delete('admin/delete-sales-reps/{user_id}', [SaleRepController::class, 'destroySingleSalesReps']);
    Route::get('admin/customers/{customerId}/recurring-payments-pending', [SaleRepController::class, 'getRecurringPaymentsPendingByCustomer']);

    Route::post('admin/sales-reps/{salesRepId}/update-status', [SaleRepController::class, 'updateSalesRepStatus']);

    Route::get('admin/get-custom-all-sales-reps', [SaleRepController::class, 'getCustomeAllSalesReps']);
    Route::get('admin/get-custom-single-sales-rep/{user_id}', [SaleRepController::class, 'getCustomeSingleSalesReps']);
    Route::get('admin/get-custom-single-sales-reps/{user_id}', [SaleRepController::class, 'getCustomSingleSalesRep']);
    // Route::get('/get-earnings-single-sales-reps', [SaleRepController::class, 'getEarningsSingleSalesRep']);
    Route::get('admin/get-earnings-all-sales-reps', [SaleRepController::class, 'getEarningsAllSalesRep']);
    Route::get('admin/get-earnings-all-sales-reps-test', [SaleRepController::class, 'getEarningsAllSalesReptest']);

    //Earnings
    Route::get('admin/salesreps/earnings', [EarningController::class, 'allSalesRepsEarnings']);
    Route::get('admin/get-single-sale-reps-earnings/{id}', [EarningController::class, 'singleSalesRepEarnings']);
    Route::post('admin/add-sale-reps-credits', [EarningController::class, 'addCredits']);
    Route::post('admin/deduct-sale-reps-credits', [EarningController::class, 'deductCredits']);
    Route::get('admin/get-single-earnings-by-month/{id}/{month}', [EarningController::class, 'showByMonth']);
    Route::patch('admin/update-sale-reps-earnings/{id}', [EarningController::class, 'update']);
    Route::delete('admin/delete-sale-reps-earnings/{id}', [EarningController::class, 'delete']);


    //LOGIC
    Route::patch('admin/sales-reps-edit-performance-number/{salesRepId}', [SaleRepController::class, 'editPerformanceNumber']);
    Route::patch('admin/sales-reps-edit-comission/{salesRepId}', [SaleRepController::class, 'editSalesRepsComission']);
    // Route::get('api/sales-reps/{salesRepId}/calculate-commissions/{month}', [SalesRepController::class, 'calculateCommissions']);
    // Route::get('api/sales-reps/{salesRepId}/check-payment-status/{month}', [SalesRepController::class, 'checkPaymentStatus']);

    // Customer
    Route::get('admin/get-all-customers', [CustomerController::class, 'getAllCustomers']);
    Route::get('admin/get-single-customer/{user_id}', [CustomerController::class, 'getSingleCustomer']);
    Route::post('admin/add-customer', [CustomerController::class, 'storeCustomer']);
    Route::patch('admin/update-customer/{user_id}', [CustomerController::class, 'updateCustomer']);
    Route::patch('admin/add-change-customer-salerep/{customer_id}', [CustomerController::class, 'addOrChangeSaleRep']);
    Route::delete('admin/delete-customer/{client_id}', [CustomerController::class, 'destroySingleCustomer']);
    Route::get('admin/get-custom-all-customers', [CustomerController::class, 'getCustomeAllCustomers']);
    Route::get('admin/get-custom-single-customer/{clientId}', [CustomerController::class, 'getSingleCustomerData']);
    Route::patch('admin/update-customer-status/{customer_id}', [CustomerController::class, 'updateCustomerStatus']);
    Route::patch('admin/update-customer-subscription-status/{subscriptionId}/{customerId}', [SubscriptionController::class, 'updateCustomerSubscriptionStatus']);

    //Advice Local Client
    Route::get('admin/get-advice-single-client/{client_id}', [CustomerController::class, 'getAdviceLocalClient']);
    Route::post('admin/update-advice-single-client/{client_id}', [CustomerController::class, 'updateAdviceLocalClient']);
    Route::post('admin/update-advice-single-client-json/{client_id}', [CustomerController::class, 'updateAdviceLocalClientRawJson']);
    Route::post('admin/update-advice-single-client-gallery-image-upload/{client_id}', [CustomerController::class, 'uploadAdviceLocalClientImage']);
    Route::post('admin/update-advice-single-client-tag-image-upload/{client_id}/{tag}', [CustomerController::class, 'uploadAdviceLocalClientImageWithTag']);
    Route::get('admin/get-advice-single-client-all-images/{client_id}', [CustomerController::class, 'getAdviceLocalClientAllImages']);
    Route::delete('admin/delete-advice-single-client-image/{client_id}/{image_name}', [CustomerController::class, 'deleteAdviceLocalClientImage']);
    Route::get('admin/get-advice-local-client-report/{client_id}', [CustomerController::class, 'getAdviceLocalClientReport']);
    Route::get('admin/check-and-fetch-advice-local-report/{client_id}', [CustomerController::class, 'checkAndFetchAdviceLocalReport']);
    Route::post('admin/update-advice-local-client-status/{client_id}', [CustomerController::class, 'updateAdviceLocalClientStatus']);
    Route::delete('admin/delete-advicelocal-order/{subscription_id}', [CustomerController::class, 'deleteAdviceLocalOrder']);

    //Package
    Route::post('admin/add-package', [PackageController::class, 'storePackage']);
    Route::put('admin/update-package/{packageId}', [PackageController::class, 'updatePackage']);
    Route::delete('admin/delete-package/{packageId}', [PackageController::class, 'destroySinglePackage']);
    Route::patch('admin/activate-package/{packageId}', [PackageController::class, 'activatePackage']);

    //FAQ
    Route::get('admin/get-all-faqs', [FAQController::class, 'getAllFAQs']);
    Route::get('admin/get-single-faq/{id}', [FAQController::class, 'getSingleFAQ']);
    Route::post('admin/add-faq', [FAQController::class, 'addFAQ']);
    Route::put('admin/update-faq/{id}', [FAQController::class, 'updateFAQ']);
    Route::delete('admin/delete-faq/{id}', [FAQController::class, 'deleteFAQ']);
    Route::post('/faq/upload-file', [FAQController::class, 'uploadFaqFile']);

    //Subscribe to Package
    Route::post('add-subscribe', [SubscriptionController::class, 'subscribePackage']);
    Route::get('get-single-customer-subscriptions', [SubscriptionController::class, 'getCustomerSubscription']);
    Route::get('get-single-customer-subscriptions', [SubscriptionController::class, 'getCustomerSubscription']);
    Route::get('admin/get-all-customers-subscriptions', [SubscriptionController::class, 'getAllCustomerSubscriptions']);
    Route::post('admin/subscriptions/{id}/change-status', [SubscriptionController::class, 'changeSubscriptionsStatus']);
    // paypal subscription
    Route::get('show-paypal-subscription-details/{paypalSubscriptionId}', [SubscriptionController::class, 'showPaypalSubscriptionDetails']);
    Route::get('cancel-paypal-subscription/{paypalSubscriptionId}', [SubscriptionController::class, 'cancelPaypalSubscription']);
    Route::get('reactivate-paypal-subscription/{paypalSubscriptionId}', [SubscriptionController::class, 'reactivatePaypalSubscription']);
    Route::get('suspend-paypal-subscription/{paypalSubscriptionId}', [SubscriptionController::class, 'suspendPaypalSubscription']);

    //payments
    Route::get('get-single-customer-payments', [PaymentController::class, 'getCustomerPayment']);
    Route::get('get-single-payments/{id}', [PaymentController::class, 'getSinglePayment']);
    Route::get('admin/get-all-customers-payments', [PaymentController::class, 'getAllCustomerPayments']);
    Route::get('admin/get-all-customers-custom-payments', [PaymentController::class, 'getPaymentsAllCustomer']);
    Route::get('admin/get-all-salesreps-custom-payments', [PaymentController::class, 'getPaymentsAllSalesreps']);

    //All Reports
    Route::get('admin/get-custom-reports', [AdminController::class, 'getCustomReportData']);


    //Documents
    Route::get('/get-all-documents', [DocumentController::class, 'getAllDocuments']);
    Route::get('/get-all-single-documents/{id}', [DocumentController::class, 'getDocumentById']);
    Route::post('/add-documents', [DocumentController::class, 'storeDocuments']);
    Route::put('/update-documents/{id}', [DocumentController::class, 'updateDocuments']);
    Route::delete('/delete-documents/{id}', [DocumentController::class, 'destroyDocuments']);
    // update position of documents
    Route::put('/documents/update-position', [DocumentController::class, 'updatePosition']);
    // upload file
    Route::post('/document/upload-file', [DocumentController::class, 'uploadDocumentFile']);
    // Update Photo, ID Card, and 1099 form file path by Sales reps ID
    Route::put('/sales-reps/{id}/update-files', [SaleRepController::class, 'updateFilesForSalesReps']);


    //Setting
    Route::get('/get-all-settings', [SettingController::class, 'getAllSettings']);
    Route::post('/add-settings', [SettingController::class, 'storeSettings']);
    Route::put('/update-settings/{id}', [SettingController::class, 'updateSettings']);
    Route::delete('/delete-settings/{id}', [SettingController::class, 'destroySettings']);

    //Partners
    Route::get('/get-all-partners', [PartnerController::class, 'getAllPartners']);
    Route::post('/add-partner', [PartnerController::class, 'storePartner']);
    Route::delete('/delete-partner/{id}', [PartnerController::class, 'destroyPartner']);

    //Third Party Costs
    Route::get('/get-all-costs', [PartnerController::class, 'getAllCosts']);
    Route::post('/add-cost', [PartnerController::class, 'storeCost']);
    Route::delete('/delete-cost/{id}', [PartnerController::class, 'destroyCost']);

    //Sales Reps Invoices
    Route::get('/get-all-sales-reps-invoices', [SaleRepController::class, 'getAllsalesRepsInvoiceList']);
    Route::get('/get-single-sales-rep-invoice/{id}', [SaleRepController::class, 'getSingleSalesRepInvoice']);

    //Sales Reps Commission
    Route::get('/get-all-sales-reps-commissions', [SaleRepController::class, 'getAllsalesRepsCommissionList']);

    //Sales Reps Stats
    Route::get('/all-sales-reps-stats', [SaleRepController::class, 'getStats']);
    Route::get('/all-sales-reps-stats-from-admin-dashboard/{salesreps_userId}', [SaleRepController::class, 'getAdminStats']);

    //Sales Reps Bar CHarts
    Route::get('/get-sales-rep-barchart-twoMonthsAgoEarningsAndCustomersCount', [SaleRepController::class, 'twoMonthsAgoEarningsAndCustomersCount']);
    Route::get('/get-sales-rep-barchart-lastMonthsEarningsAndCustomersCount', [SaleRepController::class, 'lastMonthsEarningsAndCustomersCount']);
    Route::get('/get-sales-rep-barchart-thisMonthsEarningsAndCustomersCount', [SaleRepController::class, 'thisMonthsEarningsAndCustomersCount']);
    Route::get('/get-sales-rep-barchart-futureEarningsAndCustomersCount', [SaleRepController::class, 'futureEarningsAndCustomersCount']);

    //All Invoices
    Route::get('/get-all-customers-invoices', [InvoiceController::class, 'getInvoicesAllCustomer']);
    Route::get('/get-single-customer-invoice/{userId}/invoices/{invoiceId}', [InvoiceController::class, 'getInvoiceForCustomer']);
    Route::get('/get-all-salesreps-invoices', [InvoiceController::class, 'getInvoicesAllSalesreps']);
    Route::get('/get-single-salesrep-invoice/{userId}/invoices/{invoiceId}', [InvoiceController::class, 'getInvoiceForSalesRep']);




    //for customer test 
    Route::get('/get-single-customers-subscriptions/{userId}', [SubscriptionController::class, 'get_single_customer_subscriptions_list']);
});
