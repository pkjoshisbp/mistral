<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'subscription_id',
        'user_id',
        'organization_id',
        'billing_period',
        'period_start',
        'period_end',
        'subtotal',
        'overage_charges',
        'total_amount',
        'currency',
        'status',
        'payment_date',
        'payment_method',
        'payment_transaction_id',
        'invoice_data'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'subtotal' => 'decimal:2',
        'overage_charges' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'invoice_data' => 'array'
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function generateInvoiceNumber()
    {
        $prefix = 'INV';
        $date = Carbon::now()->format('Ym');
        $lastInvoice = Invoice::where('invoice_number', 'like', "{$prefix}-{$date}-%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "{$prefix}-{$date}-{$newNumber}";
    }

    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function getFormattedTotalAttribute()
    {
        return '$' . number_format($this->total_amount, 2);
    }

    public function getFormattedPeriodAttribute()
    {
        return $this->period_start->format('M d, Y') . ' - ' . $this->period_end->format('M d, Y');
    }
}
