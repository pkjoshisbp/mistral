<x-mail::message>
# Subscription Confirmed!

Hello {{ $user->name }},

Thank you for subscribing to our AI Chat Support service! Your subscription has been successfully activated.

## Subscription Details

**Plan:** {{ $planName }}
**Billing Cycle:** {{ ucfirst($billingCycle) }}
**Next Billing Date:** {{ $subscription->current_period_end ? $subscription->current_period_end->format('F d, Y') : 'N/A' }}
**Status:** {{ ucfirst($subscription->status ?? 'Active') }}

## What's Next?

You can now access all the features of your subscription plan. Here's what you can do:

- Access your dashboard to manage your AI chat settings
- Configure your organization's AI support system
- Upload and manage your knowledge base
- Monitor chat analytics and performance

<x-mail::button :url="url('/customer/dashboard')">
Access Your Dashboard
</x-mail::button>

If you have any questions or need help getting started, feel free to contact our support team.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
