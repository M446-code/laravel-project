<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = ['transaction_id','description', 'amount','payment_type','subscription_id', 'payment_method_id','paypal_subscription_id','customer_id','result_type'];
    
    
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'transaction_id', 'transaction_id');
    }

    public function paymentMethod()
{
    return $this->belongsTo(PaymentMethod::class);
}

public function subscription()
{
    return $this->belongsTo(Subscription::class, 'subscription_id');
}

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

}

