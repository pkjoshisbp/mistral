<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Services\AiAgentService;

class LegalContentSeeder extends Seeder
{
    protected $aiAgentService;

    public function __construct(AiAgentService $aiAgentService)
    {
        $this->aiAgentService = $aiAgentService;
    }

    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $organization = Organization::where('slug', 'ai-chat-support')->first();
        
        if (!$organization) {
            $this->command->error('AI Chat Support organization not found');
            return;
        }

        $legalDocuments = [
            [
                'title' => 'Privacy Policy',
                'content' => 'Privacy Policy

Last updated: ' . date('F j, Y') . '

AI Chat Support ("we," "our," or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website and use our AI chat support services.

INFORMATION WE COLLECT

Personal Information
• Name and contact information (email address, phone number)
• Account credentials (username, encrypted passwords)
• Organization details and business information
• Chat conversation data and support interactions
• Payment and billing information

Technical Information
• IP addresses and device information
• Browser type and version
• Usage data and analytics
• Cookies and tracking technologies
• Log files and system data

HOW WE USE YOUR INFORMATION

We use the collected information to:
• Provide and maintain our AI chat support services
• Process your transactions and manage your account
• Improve our services through AI training and optimization
• Communicate with you about your account and our services
• Comply with legal obligations and prevent fraud
• Analyze usage patterns to enhance user experience

INFORMATION SHARING

We do not sell, trade, or rent your personal information to third parties. We may share your information only in the following circumstances:
• With your explicit consent
• To comply with legal requirements or court orders
• To protect our rights, property, or safety
• With trusted service providers who assist in our operations
• In connection with a business transfer or acquisition

DATA SECURITY

We implement industry-standard security measures to protect your information:
• Encryption of data in transit and at rest
• Regular security audits and assessments
• Access controls and authentication systems
• Secure data centers and infrastructure
• Employee training on data protection

YOUR RIGHTS

You have the right to:
• Access your personal information
• Correct inaccurate or incomplete data
• Delete your account and associated data
• Export your data in a portable format
• Opt-out of marketing communications
• Withdraw consent for data processing

COOKIES AND TRACKING

We use cookies and similar technologies to:
• Remember your preferences and settings
• Analyze website traffic and usage patterns
• Improve our services and user experience
• Provide targeted content and advertisements

You can control cookie settings through your browser preferences.

DATA RETENTION

We retain your information for as long as necessary to:
• Provide our services to you
• Comply with legal obligations
• Resolve disputes and enforce agreements
• Improve our AI models and services

When you delete your account, we will remove your personal information within 30 days, except where retention is required by law.

CHILDREN\'S PRIVACY

Our services are not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If we become aware that we have collected such information, we will take steps to delete it promptly.

INTERNATIONAL TRANSFERS

Your information may be transferred to and processed in countries other than your country of residence. We ensure appropriate safeguards are in place to protect your information during international transfers.

UPDATES TO THIS POLICY

We may update this Privacy Policy from time to time. We will notify you of any material changes by posting the new Privacy Policy on this page and updating the "Last updated" date.

CONTACT US

If you have any questions about this Privacy Policy, please contact us:
• Email: privacy@ai-chat.support
• Address: AI Chat Support Privacy Office
• Phone: +1 (555) 123-4567

By using our services, you acknowledge that you have read and understood this Privacy Policy.',
                'metadata' => [
                    'type' => 'legal',
                    'category' => 'privacy_policy',
                    'language' => 'en',
                    'version' => '1.0'
                ]
            ],
            [
                'title' => 'Terms of Service',
                'content' => 'Terms of Service

Last updated: ' . date('F j, Y') . '

Welcome to AI Chat Support. These Terms of Service ("Terms") govern your use of our website and AI chat support services operated by AI Chat Support ("us," "we," or "our").

ACCEPTANCE OF TERMS

By accessing or using our services, you agree to be bound by these Terms. If you disagree with any part of these terms, you may not access our services.

DESCRIPTION OF SERVICE

AI Chat Support provides AI-powered chat support solutions for businesses and organizations. Our services include:
• AI chatbot deployment and management
• Customer support automation
• Knowledge base integration
• Analytics and reporting tools
• Custom AI training and optimization

USER ACCOUNTS

To use our services, you must:
• Provide accurate and complete registration information
• Maintain the security of your account credentials
• Notify us immediately of any unauthorized access
• Be responsible for all activities under your account
• Be at least 18 years old or have parental consent

ACCEPTABLE USE

You agree to use our services only for lawful purposes and in accordance with these Terms. You must not:
• Violate any applicable laws or regulations
• Transmit harmful, offensive, or inappropriate content
• Attempt to gain unauthorized access to our systems
• Use our services for spam, phishing, or malicious activities
• Reverse engineer or attempt to extract our AI models
• Interfere with the operation of our services

SERVICE AVAILABILITY

We strive to maintain high service availability but cannot guarantee uninterrupted access. We may:
• Perform scheduled maintenance with advance notice
• Suspend services for emergency maintenance
• Modify or discontinue features with reasonable notice
• Implement usage limits to ensure fair access

PRICING AND PAYMENT

Our pricing is based on usage tiers and subscription plans:
• Starter Plan: $29/month - Up to 1,000 conversations
• Professional Plan: $99/month - Up to 10,000 conversations
• Enterprise Plan: $299/month - Unlimited conversations
• Custom pricing available for large organizations

Payment terms:
• Charges are billed monthly or annually in advance
• All fees are non-refundable except as required by law
• We may change pricing with 30 days notice
• Accounts may be suspended for non-payment

INTELLECTUAL PROPERTY

Our services and content are protected by intellectual property laws:
• We retain all rights to our AI models and technology
• You retain ownership of your data and content
• You grant us limited rights to use your data to provide services
• Feedback and suggestions may be used without compensation

PRIVACY AND DATA PROTECTION

Your privacy is important to us:
• We collect and use data as described in our Privacy Policy
• Your data is encrypted and securely stored
• We do not sell your personal information to third parties
• You can request data deletion upon account termination

LIMITATION OF LIABILITY

To the maximum extent permitted by law:
• Our services are provided "as is" without warranties
• We are not liable for indirect, incidental, or consequential damages
• Our total liability is limited to the amount paid for services
• We are not responsible for third-party content or services

INDEMNIFICATION

You agree to indemnify and hold us harmless from any claims, damages, or expenses arising from:
• Your use of our services
• Your violation of these Terms
• Your infringement of third-party rights
• Your content or data submitted to our services

TERMINATION

Either party may terminate these Terms:
• You may cancel your account at any time
• We may suspend or terminate accounts for Terms violations
• Termination does not relieve payment obligations
• Certain provisions survive termination

DISPUTE RESOLUTION

Any disputes will be resolved through:
• Good faith negotiations first
• Binding arbitration if negotiations fail
• Arbitration conducted under AAA Commercial Rules
• Disputes resolved individually, not as class actions

GOVERNING LAW

These Terms are governed by the laws of [Your Jurisdiction] without regard to conflict of law principles.

CHANGES TO TERMS

We may modify these Terms at any time:
• Material changes will be posted with 30 days notice
• Continued use constitutes acceptance of changes
• You may terminate your account if you disagree with changes

CONTACT INFORMATION

For questions about these Terms, contact us:
• Email: legal@ai-chat.support
• Address: AI Chat Support Legal Department
• Phone: +1 (555) 123-4567

ENTIRE AGREEMENT

These Terms, along with our Privacy Policy, constitute the entire agreement between you and AI Chat Support regarding your use of our services.',
                'metadata' => [
                    'type' => 'legal',
                    'category' => 'terms_of_service',
                    'language' => 'en',
                    'version' => '1.0'
                ]
            ]
        ];

        foreach ($legalDocuments as $document) {
            try {
                $success = $this->aiAgentService->storeData(
                    $organization->id,
                    'legal',
                    $document['title'],
                    $document['content'],
                    $document['metadata']
                );

                if ($success) {
                    $this->command->info("✓ Added: {$document['title']}");
                } else {
                    $this->command->error("✗ Failed to add: {$document['title']}");
                }
            } catch (\Exception $e) {
                $this->command->error("✗ Error adding {$document['title']}: " . $e->getMessage());
            }
        }

        $this->command->info('Legal content seeding completed!');
    }
}
