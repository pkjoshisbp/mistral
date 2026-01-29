# AI Chat Support - Magento 2 Composer Package

## Features
- Injects the AI Chat widget on all storefront pages
- Admin config to enable/disable and set organization slug or ID

## Composer Installation
1) Add the repository (Packagist or VCS) in your Magento `composer.json`
2) Run:
   - composer require aichat/magento2-widget
   - php bin/magento setup:upgrade
   - php bin/magento cache:flush

## Configuration
Stores → Configuration → General → AI Chat Support

## Widget Script
Loads from:
- https://ai-chat.support/widget/{org_slug_or_id}/script.js
