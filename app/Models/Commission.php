<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = ['sales_rep_id', 'month', 'commission_type', 'commission_amount', 'deduction', 'balance', 'paid', 'customer_id'];

    public function salesRep()
    {
        return $this->belongsTo(SaleRep::class, 'sales_rep_id', 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'user_id');
    }
}
