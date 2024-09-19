<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceSr extends Model
{
    use HasFactory;
    protected $table = 'invoices_srs';

    protected $fillable = [
        'role','role_user_id','user_status','date','month','recurring_amount','setup_fee','total_amount','invoice_type','transaction_id','paypal_subscription_id','status'];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'role_user_id');
    }

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
