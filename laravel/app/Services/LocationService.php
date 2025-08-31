<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class LocationService
{
    /**
     * Get user's country from IP address
     */
    public function getUserCountry($ip = null)
    {
        if (!$ip) {
            $ip = request()->ip();
        }

        // Skip for local IPs
        if ($this->isLocalIP($ip)) {
            return 'US'; // Default for local development
        }

        $cacheKey = "location_" . md5($ip);
        
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($ip) {
            try {
                // Use ipapi.co for location detection (free tier)
                $response = Http::timeout(5)->get("https://ipapi.co/{$ip}/country/");
                
                if ($response->successful()) {
                    return $response->body();
                }
                
                // Fallback to ip-api.com
                $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}?fields=countryCode");
                
                if ($response->successful()) {
                    $data = $response->json();
                    return $data['countryCode'] ?? 'US';
                }
                
                return 'US'; // Default fallback
                
            } catch (\Exception $e) {
                \Log::warning('Location detection failed: ' . $e->getMessage());
                return 'US'; // Default fallback
            }
        });
    }

    /**
     * Check if IP is local/private
     */
    private function isLocalIP($ip)
    {
        return in_array($ip, ['127.0.0.1', '::1']) || 
               filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Check if user is from India
     */
    public function isFromIndia($ip = null)
    {
        return $this->getUserCountry($ip) === 'IN';
    }

    /**
     * Get currency based on location
     */
    public function getUserCurrency($ip = null)
    {
        return $this->isFromIndia($ip) ? 'INR' : 'USD';
    }

    /**
     * Convert USD to INR (multiply by 100 as requested)
     */
    public function convertToINR($usdAmount)
    {
        return $usdAmount * 100;
    }

    /**
     * Format price based on currency
     */
    public function formatPrice($amount, $currency = null, $ip = null)
    {
        if (!$currency) {
            $currency = $this->getUserCurrency($ip);
        }

        if ($currency === 'INR') {
            return '₹' . number_format($amount, 0);
        }

        return '$' . number_format($amount, 0);
    }
}
