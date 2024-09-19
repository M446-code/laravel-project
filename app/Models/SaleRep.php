<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleRep extends Model
{
    use HasFactory;
    protected $table = 'sale_reps';

    // protected $fillable = ['user_id', 'username', 'payment_method', 'commission'];
    protected $fillable = [
        'user_id',
        'username',
        'family_name',
        'business_name',
        'address',
        'zip',
        'city',
        'state',
        'mobile',
        'paypal_account',
        'payment_method',
        'commission',
        'photo_path',
        'id_card_front_path',
        'id_card_back_path',
        'form_1099_path',
        'i9_path',
        'w9_path'
    ];
    public function performanceNumbers()
    {
        return $this->hasMany(PerformanceNumber::class, 'sales_rep_id', 'user_id');
    }
    
    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id'); // 'user_id' refers to the foreign key in the 'customers' table.
    }
}
