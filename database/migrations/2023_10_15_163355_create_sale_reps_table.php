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
        Schema::create('sale_reps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username', 20)
                ->unique()
                ->nullable(false)
                ->min(4)
                ->max(20);
            $table->string('family_name')->nullable();
            $table->string('business_name')->nullable();
            $table->string('address')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('mobile')->nullable();
            $table->string('paypal_account')->nullable();
            $table->unsignedBigInteger('payment_method')->nullable();
            $table->unsignedBigInteger('commission')->nullable();
            $table->string('photo_path')->nullable(); // Assuming you store the path to the uploaded photo
            $table->string('id_card_front_path')->nullable(); // Assuming you store the path to the uploaded ID card/driving license
            $table->string('id_card_back_path')->nullable(); 
            $table->string('form_1099_path')->nullable(); 
            $table->string('i9_path')->nullable();
            $table->string('w9_path')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_reps');
    }
};

