# AI Chat Support - Magento 2 Extension

## Features
- Injects the AI Chat widget on all storefront pages
- Admin config to enable/disable and set organization slug or ID

## Installation
1) Upload the `app` folder into your Magento root (merge with existing `app` directory)
2) Run Magento setup commands:
   - php bin/magento setup:upgrade
   - php bin/magento cache:flush
3) Configure in Admin:
   - Stores → Configuration → General → AI Chat Support
   - Enable widget and set Organization Slug or ID

## Widget Script
Loads from:
- https://ai-chat.support/widget/{org_slug_or_id}/script.js

## Notes
- Use a valid organization slug (preferred) or numeric ID
- Works for multi-store views (set per website/store if needed)
