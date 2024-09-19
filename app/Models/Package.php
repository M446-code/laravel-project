<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;
    protected $table = 'packages';

    protected $fillable = ['title', 'description', 'monthly_price', 'term_months', 'paypal_product_id', 'paypal_plan_id', 'setup_cost', 'is_advice_local_enabled', 'advice_local_products', 'status'];
}
