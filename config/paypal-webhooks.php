<?php

declare(strict_types=1);

return [
    /*
     * You can define the job that should run when a certain webhook hits your application
     * here. See the examples below for key names.
     *
     * You can find a list of event types here:
     * https://developer.paypal.com/api/rest/webhooks/event-names/#orders
     */
    'jobs' => [
        // 'checkout_order_approved' => \App\Jobs\Webhook\PayPal\CheckoutOrderApprovedJob::class,
        'billing_subscription_created' => \App\Jobs\ProcessBillingSubscriptionCreated::class,
        'payment_sale_completed' => \App\Jobs\ProcessPaymentSaleCompleted::class,
        'billing_subscription_payment_failed' => \App\Jobs\ProcessBillingSubscriptionPaymentFailed::class,
        'payment_sale_denied' => \App\Jobs\ProcessPaymentSaleDenied::class,
        // 'payment_payoutsbatch_success' => \App\Jobs\ProcessPaymentPayoutsBatchSuccess::class,
        'payment_payouts-item_succeeded' => \App\Jobs\ProcessPaymentPayoutsItemSucceed::class,
        'payment_payouts-item_unclaimed' => \App\Jobs\ProcessPaymentPayoutsItemUnclaimed::class,


    ],

    /*
    * The classname of the model to be used. The class should equal or extend
    * \Ankurk91\PayPalWebhooks\Model\PayPalWebhookCall.
    */
    'model' => \Ankurk91\PayPalWebhooks\Model\PayPalWebhookCall::class,

    /**
     * This class determines if the incoming webhook call should be stored and processed.
     */
    'profile' => \Ankurk91\PayPalWebhooks\PayPalWebhookProfile::class,

    /*
     * When disabled, the package will not verify if the signature is valid.
     * This can be handy in local environments and testing.
     */
    'verify_signature' => (bool) env('PAYPAL_SIGNATURE_VERIFY', true),

    /*
     * The ID of the webhook resource for the destination URL to which PayPal delivers the event notification.
     * Required for signature verification.
     */
    'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
];
