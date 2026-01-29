# Magento Marketplace Submission Checklist (AI Chat Support)

## Package & Metadata
- Extension name: AI Chat Support
- Module name: AiChat_Widget
- Version: 1.0.0
- Type: Magento 2 Module
- Compatibility: Magento 2.3+ (add tested versions)
- License: Proprietary (or switch to OSL-3.0 if required)
- Vendor: AI Chat Support
- Support URL: https://ai-chat.support
- Contact Email: support@ai-chat.support

## Required Assets
- Extension ZIP (manual install)
- Composer package (magento2-module)
- README with install + config steps
- 3–5 screenshots (admin config + storefront)
- Icon (128x128) + logo (512x512)
- Short description + long description
- Changelog

## Compliance & Security
- Uses only frontend script injection via https://ai-chat.support/widget/{org_slug}/script.js
- No customer data stored in Magento
- No PII collected by extension itself
- Config stored in Magento system config only
- No external API keys required

## Test Checklist
- Install via Composer
- Install via manual ZIP
- Enable/disable module
- Store view scope settings work
- Frontend widget loads on all pages
- No console errors

## Submission Notes
- Provide GDPR statement (no personal data stored by module)
- List external service dependency (ai-chat.support)
- Provide uninstall steps (disable module + remove files)
