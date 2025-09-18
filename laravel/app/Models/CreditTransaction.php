<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'metadata',
        'reference_id',
        'subscription_id',
        'credit_package_id',
        'credits',
        'payment_method',
        'razorpay_payment_id',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'credits' => 'decimal:4',
        'metadata' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function creditPackage()
    {
        return $this->belongsTo(CreditPackage::class);
    }
}