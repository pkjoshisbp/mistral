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
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'metadata' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}