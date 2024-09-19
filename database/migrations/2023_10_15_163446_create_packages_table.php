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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Title of the package
            $table->text('description'); // Description of the package
            $table->decimal('monthly_price', 10, 2); // Monthly price (decimal with 10 total digits and 2 decimal places)
            $table->unsignedInteger('term_months'); // Number of term months (non-negative integer)
            $table->string('paypal_product_id')->nullable(); // PayPal product ID (string)
            $table->string('paypal_plan_id')->nullable(); // PayPal plan ID (string)
            $table->decimal('setup_cost', 10, 2)->default(0.00);
            $table->boolean('is_advice_local_enabled')->default(false);
            $table->json('advice_local_products')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
