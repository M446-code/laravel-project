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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->text('description');
            $table->decimal('amount', 10, 2);
            $table->string('payment_type')->nullable();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('payment_method_id');
            $table->string('paypal_subscription_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->string('transaction_id'); // Add this line for transaction_id
            $table->string('result_type')->nullable();
            $table->timestamps();

            // Define foreign key constraints
            // $table->foreign('subscription_id')->references('id')->on('subscriptions');
            // $table->foreign('payment_method_id')->references('id')->on('payment_methods');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
