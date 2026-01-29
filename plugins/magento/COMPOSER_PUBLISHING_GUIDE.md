# Magento Composer Publishing Guide (AI Chat Support)

## Option A: Packagist (recommended)
1) Create Git repo with composer package structure
2) Set repository public
3) Add to Packagist: https://packagist.org/packages/submit
4) Use name: aichat/magento2-widget
5) Tag release: v1.0.0

## Option B: Private VCS Repo
- Add repository to Magento `composer.json`:
  {
    "repositories": [
      {"type": "vcs", "url": "https://github.com/your-org/magento2-widget"}
    ]
  }

## Install Commands
- composer require aichat/magento2-widget
- php bin/magento setup:upgrade
- php bin/magento cache:flush

## Required Files
- composer.json (type=magento2-module)
- registration.php
- etc/module.xml
- etc/adminhtml/system.xml
- view/frontend/layout/default.xml
- view/frontend/templates/widget.phtml

## Versioning
- Use semver tags: v1.0.0, v1.0.1
- Update composer.json version
- Update module.xml setup_version
