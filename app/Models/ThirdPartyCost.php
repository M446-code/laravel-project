<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThirdPartyCost extends Model
{
    use HasFactory;
    protected $fillable = ['partner_id', 'cost_type', 'cost_amount'];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
