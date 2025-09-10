# Analytics Implementation Guide

## For Website Owners

### Quick Setup

1. **Add to your website header:**
```html
<script>
  window.ANALYTICS_ORG_ID = YOUR_ORG_ID; // Replace with your organization ID
</script>
<script src="https://ai-chat.support/js/simple-analytics.js" async></script>
```

2. **That's it!** The script will automatically track:
   - Page views
   - Unique visitors  
   - Time spent on pages
   - Click events
   - Scroll depth
   - Geographic location
   - Referrer information

### Advanced Configuration

```html
<script>
  window.ANALYTICS_ORG_ID = 3; // Your organization ID
  window.ANALYTICS_CONFIG = {
    trackClicks: true,      // Track button/link clicks
    trackScrolling: true,   // Track scroll depth
    debug: false,          // Enable console logging
  };
</script>
<script src="https://ai-chat.support/js/simple-analytics.js" async></script>
```

## Benefits Over Google Analytics

✅ **Lightweight** - Only 4KB vs Google's 45KB  
✅ **Fast Loading** - No external dependencies  
✅ **Privacy Friendly** - No cookies, GDPR compliant  
✅ **Real-time Data** - Instant tracking and reporting  
✅ **Simple Setup** - One script, no complex configuration  
✅ **No Ads** - Your data stays private, no advertising network  

## Admin Dashboard Features

- **Live Visitor Tracking** - See visitors in real-time
- **Page Performance** - Top pages, time on site, bounce rate
- **Geographic Data** - Visitor countries and regions  
- **Widget Analytics** - Chat interactions and engagement
- **Custom Events** - Track specific user actions
- **Historical Data** - 1 day to 90 days reporting

## View Your Analytics

Admin users can access the analytics dashboard at:
`/admin/analytics`

Select your organization and time period to view detailed metrics.
