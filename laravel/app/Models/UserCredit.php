<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'total_purchased',
        'total_used',
        'last_updated_at'
    ];

    protected $casts = [
        'balance' => 'decimal:4',
        'total_purchased' => 'decimal:4',
        'total_used' => 'decimal:4',
        'last_updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create user credits record
     */
    public static function getOrCreateForUser($userId)
    {
        return static::firstOrCreate(['user_id' => $userId], [
            'balance' => 0,
            'total_purchased' => 0,
            'total_used' => 0,
            'last_updated_at' => now()
        ]);
    }

    /**
     * Add credits to user balance (thread-safe)
     */
    public function addCredits($amount, $reason = 'Purchase')
    {
        DB::transaction(function () use ($amount, $reason) {
            $this->increment('balance', $amount);
            $this->increment('total_purchased', $amount);
            $this->update(['last_updated_at' => now()]);
            
            // Log the transaction
            CreditTransaction::create([
                'user_id' => $this->user_id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $this->fresh()->balance,
                'description' => $reason
            ]);
        });
    }

    /**
     * Deduct credits from user balance (thread-safe)
     */
    public function deductCredits($amount, $reason = 'Usage')
    {
        return DB::transaction(function () use ($amount, $reason) {
            if ($this->balance < $amount) {
                return false; // Insufficient credits
            }
            
            $this->decrement('balance', $amount);
            $this->increment('total_used', $amount);
            $this->update(['last_updated_at' => now()]);
            
            // Log the transaction
            CreditTransaction::create([
                'user_id' => $this->user_id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $this->fresh()->balance,
                'description' => $reason
            ]);
            
            return true;
        });
    }

    /**
     * Check if user has sufficient credits
     */
    public function hasSufficientCredits($amount)
    {
        return $this->balance >= $amount;
    }
}