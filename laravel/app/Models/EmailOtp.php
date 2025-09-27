<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmailOtp extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'email',
        'otp',
        'type',
        'expires_at',
        'verified_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Check if OTP is valid and not expired
     */
    public function isValid(): bool
    {
        return !$this->verified_at && $this->expires_at->isFuture();
    }

    /**
     * Mark OTP as verified
     */
    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }

    /**
     * Generate a new OTP for an email
     */
    public static function generateForEmail(string $email, string $type = 'login'): self
    {
        // Delete any existing OTPs for this email/type
        self::where('email', $email)
            ->where('type', $type)
            ->delete();

        $record = self::create([
            'email' => $email,
            'otp' => str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes(10) // 10 minutes expiry
        ]);

        // Log OTP for support visibility (admin-only page also lists recent OTPs)
        \Log::info('Email OTP generated', [
            'email' => $record->email,
            'type' => $record->type,
            'otp' => $record->otp,
            'expires_at' => $record->expires_at
        ]);

        return $record;
    }

    /**
     * Verify OTP for email
     */
    public static function verifyOtp(string $email, string $otp, string $type = 'login'): ?self
    {
        $otpRecord = self::where('email', $email)
            ->where('otp', $otp)
            ->where('type', $type)
            ->first();

        if ($otpRecord && $otpRecord->isValid()) {
            $otpRecord->markAsVerified();
            return $otpRecord;
        }

        return null;
    }
}
