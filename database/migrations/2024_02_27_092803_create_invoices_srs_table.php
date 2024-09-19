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
        Schema::create('invoices_srs', function (Blueprint $table) {
            $table->id();
            $table->string('role', 191);
            $table->unsignedBigInteger('role_user_id');
            $table->string('user_status', 191)->nullable();
            $table->date('date')->nullable();
            $table->string('month', 255)->nullable();
            $table->decimal('recurring_amount', 10, 2)->nullable();
            $table->decimal('setup_fee', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->string('invoice_type', 191)->nullable();
            $table->string('transaction_id', 191)->nullable();
            $table->string('paypal_subscription_id')->nullable();
            $table->enum('status', ['unpaid', 'paid', 'failed'])->default('unpaid');
            $table->timestamps();

            // Indexes
            $table->index('role_user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices_srs');
    }
};
