<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use HasFactory;
    protected $table = 'customers';

    protected $fillable = ['user_id','client_id','payment_method','business_name','street', 'zipCode', 'country', 'state', 'city', 'referral_username'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id'); // 'user_id' refers to the foreign key in the 'customers' table.
    }

    // public static function getRecurringPaymentsPendingByCustomerId($customerId)
    // {
    //     return DB::table('customers as c')
    //         ->leftJoin('subscriptions as s', function ($join) {
    //             $join->on('c.user_id', '=', 's.customer_id')
    //                 ->where('s.status', '=', 'Active');
    //         })
    //         ->leftJoin('packages as pkg', 's.package_id', '=', 'pkg.id')
    //         ->leftJoin(DB::raw('(SELECT subscription_id, COUNT(id) AS payment_count FROM payments GROUP BY subscription_id) p'), 's.id', '=', 'p.subscription_id')
    //         ->where('c.user_id', '=', $customerId)
    //         ->groupBy('c.user_id', 'c.referral_username') // Grouping by referral_username to keep it consistent with the original query
    //         ->selectRaw('
    //             c.user_id AS customer_id,
    //             c.referral_username,
    //             SUM(IFNULL((pkg.term_months - p.payment_count) * pkg.monthly_price * (s.salesrep_commission / 100), 0)) AS total_recurring_payments_pending
    //         ')
    //         ->get();
    // }

    public static function getRecurringPaymentsPendingByCustomerId($customerId)
    {
        return DB::table('customers as c')
            ->leftJoin('subscriptions as s', function ($join) {
                $join->on('c.user_id', '=', 's.customer_id')
                    ->where('s.status', '=', 'Active');
            })
            ->leftJoin('packages as pkg', 's.package_id', '=', 'pkg.id')
            ->leftJoin(DB::raw('(SELECT subscription_id, COUNT(id) AS payment_count FROM payments GROUP BY subscription_id) p'), 's.id', '=', 'p.subscription_id')
            ->where('c.user_id', '=', $customerId)
            ->groupBy('c.user_id', 'c.referral_username') // Grouping by referral_username to keep it consistent with the original query
            ->selectRaw('
                c.user_id AS customer_id,
                c.referral_username,
                SUM(IFNULL((pkg.term_months - p.payment_count) * pkg.monthly_price, 0)) AS total_recurring_payments_pending
            ')
            ->get();
    }

}
