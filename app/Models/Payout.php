<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    use HasFactory;
    protected $fillable = [
        'role',
        'role_user_id',
        'invoice_id',
        'month',
        'amount',
        'payout_item_id',
        'paypal_transaction_id',
        'result_type',
        'sale_reps_status',
        'payout_batch_id',
    ];

    public function invoice()
    {
        return $this->belongsTo(InvoiceSr::class, 'invoice_id', 'id');
    }

    public function saleRep()
    {
        return $this->belongsTo(SaleRep::class, 'role_user_id', 'user_id');
    }
}
