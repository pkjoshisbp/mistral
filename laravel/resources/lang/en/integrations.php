<?php
return [
    'page_title' => 'Integrations',
    'page_subtitle' => 'Easy integration with your favorite platforms. Install our AI Chat Support system on WordPress, WooCommerce, Magento, and Shopify with just a few clicks.',
    
    'description' => 'Description',
    'features' => 'Features:',
    'requirements' => 'Requirements:',
    
    'download_button' => 'Download :platform Plugin',
    'download_size' => ':size',
    'install_button' => 'Install :platform App',
    
    'wordpress_note' => '<strong>Note:</strong> You can also find this plugin on the official WordPress.org repository.',
    'magento_note' => '<strong>Note:</strong> Upload the ZIP contents into your Magento root directory.',
    'magento_composer_button' => 'Download Composer Package',
    'shopify_note' => '<strong>Note:</strong> Also available on the Shopify App Store for easy discovery.',
    
    // Installation Guides
    'installation_guide' => 'Installation Guide',
    'step' => 'Step :number',
    
    'wordpress_install_steps' => [
        'Download the plugin ZIP file',
        'Go to WordPress Admin → Plugins → Add New',
        'Click "Upload Plugin" and select the downloaded ZIP',
        'Activate the plugin',
        'Configure your API settings in Settings → AI Chat Support'
    ],
    
    'magento_install_steps' => [
        'Download the extension ZIP file',
        'Extract the contents to your Magento root directory',
        'Run: php bin/magento setup:upgrade',
        'Run: php bin/magento cache:flush',
        'Configure in Stores → Configuration → AI Chat Support'
    ],
    
    'shopify_install_steps' => [
        'Click "Install Shopify App" button above',
        'Authorize the app in your Shopify admin',
        'Configure your organization slug and settings',
        'The chat widget will automatically appear on your store'
    ],
];
