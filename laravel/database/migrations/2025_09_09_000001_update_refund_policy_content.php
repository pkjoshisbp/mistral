<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the refund policy content
        DB::table('terms_and_conditions')
            ->where('type', 'refund')
            ->update([
                'title' => 'Refund Policy',
                'content' => $this->getNewRefundPolicyContent(),
                'updated_at' => now()
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration updates content - no rollback needed
    }

    private function getNewRefundPolicyContent(): string
    {
        return "Refund Policy

Last updated: September 9, 2025

AI Chat Support operates with a \"test-first\" approach through our affordable Pay-as-you-go plan. We do not offer refunds, but we provide a comprehensive testing solution to ensure you can evaluate our service before committing to higher-tier plans.

NO REFUND POLICY

We do not offer refunds on any subscription plans or payments. However, we strongly encourage all customers to:
• Start with our $5 Pay-as-you-go plan (200,000 tokens)
• Thoroughly test our AI chat capabilities
• Evaluate service quality and integration
• Upgrade to higher plans only when satisfied

TESTING APPROACH

Instead of refunds, we provide:
• Low-cost testing with $5 Pay-as-you-go plan
• 200,000 tokens (approximately 600-2,000 conversations)
• Full access to AI chat features during testing
• Complete evaluation of response quality and accuracy
• Integration testing capabilities

PLAN UPGRADES

You can upgrade your plan at any time:
• Unused tokens are credited to your new plan
• Immediate access to higher-tier features
• No additional fees for plan changes
• Seamless transition between plans

WHY NO REFUNDS?

Our no-refund policy allows us to:
• Maintain competitive pricing
• Focus resources on service quality
• Provide better support to active users
• Ensure system stability and reliability

TESTING RECOMMENDATIONS

To make the most of your $5 testing investment:
• Test various types of customer inquiries
• Evaluate response accuracy and relevance
• Try different conversation scenarios
• Test integration with your existing systems
• Monitor token usage patterns

ACCOUNT CANCELLATION

You can cancel your subscription at any time:
• Cancellation takes effect at the end of the current billing period
• No refund for unused portions
• Data retention according to our Privacy Policy
• Account reactivation available with new payment

SERVICE CREDITS

In exceptional circumstances, we may provide service credits for:
• Extended service outages (over 24 hours)
• Technical issues preventing service use
• Credits applied to your account, not cash refunds
• Case-by-case evaluation by our support team

PAYMENT DISPUTES

For payment disputes:
• Contact your payment provider directly
• We cooperate with legitimate dispute investigations
• Account may be suspended during dispute resolution
• Resolution according to payment provider policies

EXCEPTIONS TO NO-REFUND POLICY

We maintain our no-refund stance except in cases of:
• Billing errors on our part
• Unauthorized charges (fraud)
• Service not delivered due to our technical failure
• Legal requirements in specific jurisdictions

CONTACT US FOR QUESTIONS

For questions about this policy:
• Email: support@ai-chat.support
• Submit a ticket through your customer dashboard
• We're here to help you make the most of your testing

ALTERNATIVE SOLUTIONS

If our service doesn't meet your needs:
• Use your testing period to explore all features
• Consider different integration approaches
• Our support team can help optimize your setup
• Upgrade to a different plan that better fits your needs

POLICY UPDATES

We may update this Refund Policy from time to time. Changes will be posted on this page with an updated revision date. Continued use of our services constitutes acceptance of any changes.

Remember: Start with our $5 Pay-as-you-go plan to thoroughly evaluate our service risk-free before committing to larger plans. This approach ensures you make an informed decision while keeping your investment minimal.";
    }
};
