<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $table = 'subscriptions';

    protected $fillable = ['customer_id', 'package_id', 'paypal_subscription_id', 'advice_local_order_id', 'failed_payments_count', 'salesrep_commission', 'status'];

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
    public function invoice()
    {
        return $this->hasMany(Invoice::class, 'paypal_subscription_id', 'paypal_subscription_id');
    }

    public function payment()
    {
        return $this->hasMany(Payment::class, 'paypal_subscription_id', 'paypal_subscription_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'user_id');
    }
}
