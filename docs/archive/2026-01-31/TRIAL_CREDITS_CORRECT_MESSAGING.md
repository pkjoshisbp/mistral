# Trial Credits Update - Correct Information

## Important Note on Pricing

**DO NOT** mention dollar values for credits in any documentation or user communication.

### Why?
Our actual pricing is:
- **100,000 tokens = $5**
- Therefore: **20,000 tokens ≠ $20** (it's actually $1 worth)

Mentioning incorrect values would be misleading to customers.

## Correct Messaging

### ✅ CORRECT - What to Say:
- "20,000 free trial credits"
- "Enough for 20-40 conversations"
- "Perfect for testing our AI assistant"
- "Generous trial period to evaluate the service"

### ❌ INCORRECT - Never Say:
- ~~"Worth $20"~~
- ~~"$10 value"~~
- ~~"Equivalent to $X"~~
- ~~Any dollar amount~~

## Updated Trial Credits

### New Shopify Users:
```
✅ 20,000 trial credits
✅ Approximately 20-40 conversations  
✅ 15-20 days of moderate usage
✅ Full access to all features
```

### WordPress Users:
```
✅ Organization gets 20,000 token balance
✅ Users must register separately
✅ Credits assigned per user account
```

## Actual Pricing Reference (Internal Only)

| Package | Tokens | Price | Per Token |
|---------|--------|-------|-----------|
| Standard | 100,000 | $5 | $0.00005 |
| Trial | 20,000 | FREE | - |

**20,000 tokens actual value**: ~$1.00 (not $20!)

## Customer Communication Templates

### Welcome Email:
```
Welcome to AI Chat Support!

Your Shopify account has been created with 20,000 free trial credits.

This gives you:
- 20-40 full conversations with your AI assistant
- 15-20 days to thoroughly test the system
- Full access to all customization features

Start chatting and see how our AI can help your customers!
```

### Dashboard Message:
```
🎉 Trial Active!

You have 20,000 credits to test our AI assistant.
That's enough for approximately 20-40 conversations.

Go to Integration Settings to customize your chat widget.
```

### Marketing Copy:
```
✨ Generous Free Trial
Get started with 20,000 credits - enough to have dozens of conversations
and fully evaluate how our AI assistant can help your business.

No credit card required. Install and start chatting immediately.
```

## Documentation Updates Required

### Files to Check:
- [ ] All .md files - Remove $ references
- [ ] Email templates - Use credit count only  
- [ ] Welcome emails - No dollar values
- [ ] Marketing materials - Credits only
- [ ] User guides - Conversation estimates
- [ ] FAQ pages - Pricing separate from trials

## Credit Usage Estimates (Safe to Mention)

### Token/Credit Consumption:
| Action | Approximate Credits |
|--------|-------------------|
| Simple question | 200-500 |
| Complex query with context | 800-1,500 |
| Document search + response | 500-1,000 |
| Average 5-message conversation | 1,000-2,000 |

### What 20,000 Credits Provides:
- ✅ 10-20 complex multi-message conversations
- ✅ 30-40 simple question-answer exchanges  
- ✅ 2-3 weeks of moderate daily testing
- ✅ Comprehensive evaluation of AI capabilities

## Implementation Status

### Completed:
- ✅ Shopify users get 20,000 credits
- ✅ Credits granted on account creation
- ✅ Integration Settings page available
- ✅ Documentation updated (values removed)

### Code:
```php
// Correct implementation
$userCredit->addCredits(20000.00, 'Initial trial credits for Shopify app installation', [
    'source' => 'shopify_install',
    'shop' => $shop
]);
```

## Pricing Page (Separate from Trial)

When customers ask about pricing after trial:
```
📊 Credit Packages

Starter: 100,000 tokens - $5
Pro: 500,000 tokens - $20  
Business: 2,000,000 tokens - $75

Or subscribe for unlimited usage
```

**Keep trial messaging completely separate from pricing!**

---

**Key Takeaway**: Always talk about credits as "credits" or "tokens", never mention dollar values in trial messaging. The trial is generous on its own merits - 20,000 credits for thorough testing.
