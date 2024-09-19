<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceNumber extends Model
{
    use HasFactory;
    protected $fillable = ['sales_rep_id', 'performance_number'];

    public function salesRep()
    {
        return $this->belongsTo(SaleRep::class, 'sales_rep_id');
    }
}