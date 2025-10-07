@component('mail::message')
# Welcome to AI Chat Support!

Hi {{ $user->name }},

Great news! The AI Chat Support app has been successfully installed on your Shopify store: **{{ $organization->name }}**

Your AI-powered chat widget is now live and ready to assist your customers 24/7! 🎉

## Your Account Details

We've created an account for you to manage your AI chat settings:

**Email:** {{ $user->email }}  
**Temporary Password:** `{{ $password }}`

@component('mail::button', ['url' => $loginUrl])
Log In to Dashboard
@endcomponent

**Important:** For security, please log in and change your password immediately.

---

## What's Next?

### 1. Customize Your Widget
- Change colors to match your brand
- Set custom welcome messages
- Adjust widget position

### 2. Train Your AI
- Upload product information
- Add FAQs and common questions
- Import your knowledge base

### 3. Monitor Performance
- View chat analytics
- Track customer satisfaction
- Review conversation history

---

## Your Widget is Already Working!

Visit your Shopify storefront to see the AI chat widget in action. It's already answering customer questions!

**Store URL:** [{{ $organization->website }}]({{ $organization->website }})

---

## Resources

- **Dashboard:** [{{ $dashboardUrl }}]({{ $dashboardUrl }})
- **Documentation:** [https://ai-chat.support/docs](https://ai-chat.support/docs)
- **Support:** [info@ai-chat.support](mailto:info@ai-chat.support)

---

## Your Token Balance

You've been credited with **20,000 tokens** to get started. This is enough for approximately 300-1,000 conversations depending on complexity.

Need more tokens? Visit your dashboard to view pricing and upgrade options.

---

If you have any questions or need assistance, our support team is here to help!

Thanks,<br>
{{ config('app.name') }} Team

---

<small>
This is an automated email sent because the AI Chat Support app was installed on your Shopify store.
If you didn't install this app, please contact us immediately at info@ai-chat.support
</small>
@endcomponent
