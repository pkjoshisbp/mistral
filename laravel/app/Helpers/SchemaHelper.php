<?php

namespace App\Helpers;

class SchemaHelper
{
    /**
     * Generate Organization Schema for client websites
     */
    public static function organizationSchema($organization)
    {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "Organization",
            "name" => $organization->name,
            "url" => config('app.url'),
            "description" => $organization->description ?? "AI-powered customer support automation with 24/7 availability"
        ];

        // Add address if available
        if (isset($organization->settings['address'])) {
            $schema["address"] = [
                "@type" => "PostalAddress",
                "streetAddress" => $organization->settings['address']
            ];
        }

        // Add contact info if available
        if (isset($organization->settings['contact_email']) || isset($organization->settings['contact_phone'])) {
            $schema["contactPoint"] = [
                "@type" => "ContactPoint",
                "contactType" => "customer service"
            ];
            
            if (isset($organization->settings['contact_email'])) {
                $schema["contactPoint"]["email"] = $organization->settings['contact_email'];
            }
            
            if (isset($organization->settings['contact_phone'])) {
                $schema["contactPoint"]["telephone"] = $organization->settings['contact_phone'];
            }
        }

        return $schema;
    }

    /**
     * Generate Service Schema for client websites
     */
    public static function serviceSchema($organization)
    {
        return [
            "@context" => "https://schema.org",
            "@type" => "Service",
            "name" => "AI Chat Support",
            "provider" => [
                "@type" => "Organization",
                "name" => $organization->name
            ],
            "description" => "24/7 AI-powered customer support automation",
            "serviceType" => "Customer Support Automation",
            "availableChannel" => [
                "@type" => "ServiceChannel",
                "serviceType" => "Live Chat",
                "availableLanguage" => "English"
            ]
        ];
    }

    /**
     * Generate WebSite Schema for client websites
     */
    public static function websiteSchema($organization)
    {
        return [
            "@context" => "https://schema.org",
            "@type" => "WebSite",
            "name" => $organization->name,
            "url" => config('app.url'),
            "description" => $organization->description ?? "Professional business website with AI-powered customer support"
        ];
    }
}
