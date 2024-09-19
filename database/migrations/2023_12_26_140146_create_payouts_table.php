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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('role', 191);
            $table->unsignedBigInteger('role_user_id');
            $table->string('invoice_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('payout_item_id', 191)->nullable();
            $table->string('paypal_transaction_id', 191)->nullable();
            $table->string('result_type')->nullable();
            $table->string('sale_reps_status')->nullable();
            $table->string('payout_batch_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout');
    }
};
