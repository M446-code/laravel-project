<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    protected $fillable = [
        'role', 'role_user_id', 'user_status', 'date', 'month', 'recurring_amount', 'setup_fee', 'total_amount', 'invoice_type', 'transaction_id', 'paypal_subscription_id', 'status'
    ];

    public function sales_rep()
    {
        return $this->belongsTo(User::class);
    }

    // relation to sale_reps table
    public function salesReps()
    {
        return $this->belongsTo(SaleRep::class, 'role_user_id', 'user_id');
    }
}
