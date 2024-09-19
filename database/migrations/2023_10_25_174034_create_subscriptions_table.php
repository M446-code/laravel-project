<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('package_id');
            $table->string('paypal_subscription_id')->nullable();
            $table->string('advice_local_order_id')->nullable();
            $table->integer('failed_payments_count')->default(0); // Corrected here
            $table->integer('salesrep_commission')->nullable();
            $table->enum('status', ['Active', 'Suspended', 'Deleted', 'Ended'])->default('Active');
            $table->timestamps();

            // Define foreign key constraints
            // Uncomment these lines if you want to define foreign key constraints
            // $table->foreign('customer_id')->references('id')->on('users');
            // $table->foreign('package_id')->references('id')->on('packages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
