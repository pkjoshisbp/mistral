<?php

if (!function_exists('convertCurrency')) {
    function convertCurrency($amount, $fromCurrency = 'USD', $toCurrency = null)
    {
        if (!$toCurrency) {
            $locale = session('app_locale', 'en');
            $toCurrency = getCurrencyByLocale($locale);
        }
        
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }
        
        // Exchange rates (you should update these regularly or use an API)
        $rates = [
            'USD' => 1.0,
            'INR' => 83.0,  // 1 USD = 83 INR (approximate)
            'EUR' => 0.85,  // 1 USD = 0.85 EUR (approximate)
            'GBP' => 0.73,  // 1 USD = 0.73 GBP (approximate)
        ];
        
        if (!isset($rates[$fromCurrency]) || !isset($rates[$toCurrency])) {
            return $amount; // Return original if currency not supported
        }
        
        // Convert via USD
        $usdAmount = $amount / $rates[$fromCurrency];
        $convertedAmount = $usdAmount * $rates[$toCurrency];
        
        return round($convertedAmount, 2);
    }
}

if (!function_exists('getCurrencyByLocale')) {
    function getCurrencyByLocale($locale)
    {
        $currencyMap = [
            'hi' => 'INR',  // Hindi -> Indian Rupee
            'en' => 'USD',  // English -> US Dollar
            'de' => 'EUR',  // German -> Euro
            'fr' => 'EUR',  // French -> Euro
            'es' => 'EUR',  // Spanish -> Euro
            'it' => 'EUR',  // Italian -> Euro
            'pt' => 'USD',  // Portuguese -> US Dollar
            'th' => 'USD',  // Thai -> US Dollar
        ];
        
        return $currencyMap[$locale] ?? 'USD';
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $currency = null, $locale = null)
    {
        if (!$locale) {
            $locale = session('app_locale', 'en');
        }
        
        if (!$currency) {
            $currency = getCurrencyByLocale($locale);
        }
        
        $symbols = [
            'USD' => '$',
            'INR' => '₹',
            'EUR' => '€',
            'GBP' => '£',
        ];
        
        $symbol = $symbols[$currency] ?? '$';
        
        // Format based on currency
        switch ($currency) {
            case 'INR':
                // Indian numbering system (lakhs/crores)
                if ($amount >= 100000) {
                    return $symbol . number_format($amount / 100000, 1) . 'L';
                } else if ($amount >= 1000) {
                    return $symbol . number_format($amount / 1000, 1) . 'K';
                } else {
                    return $symbol . number_format($amount, 0);
                }
            default:
                return $symbol . number_format($amount, 0);
        }
    }
}

if (!function_exists('getLocalizedPricing')) {
    function getLocalizedPricing($usdPrice, $locale = null)
    {
        if (!$locale) {
            $locale = session('app_locale', 'en');
        }
        
        $currency = getCurrencyByLocale($locale);
        $convertedPrice = convertCurrency($usdPrice, 'USD', $currency);
        
        return [
            'amount' => $convertedPrice,
            'currency' => $currency,
            'formatted' => formatCurrency($convertedPrice, $currency, $locale)
        ];
    }
}
