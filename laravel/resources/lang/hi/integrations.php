<?php
return [
    'page_title' => 'एकीकरण',
    'page_subtitle' => 'अपने पसंदीदा प्लेटफ़ॉर्म के साथ आसान एकीकरण। WordPress, WooCommerce, Magento और Shopify पर हमारी AI चैट सपोर्ट सिस्टम को कुछ ही क्लिक में इंस्टॉल करें।',
    
    'description' => 'विवरण',
    'features' => 'विशेषताएं:',
    'requirements' => 'आवश्यकताएं:',
    
    'download_button' => ':platform प्लगइन डाउनलोड करें',
    'download_size' => ':size',
    'install_button' => ':platform ऐप इंस्टॉल करें',
    
    'wordpress_note' => '<strong>नोट:</strong> आप यह प्लगइन आधिकारिक WordPress.org रिपॉजिटरी पर भी पा सकते हैं।',
    'magento_note' => '<strong>नोट:</strong> ZIP की सामग्री को अपनी Magento रूट डायरेक्टरी में अपलोड करें।',
    'magento_composer_button' => 'Composer पैकेज डाउनलोड करें',
    'shopify_note' => '<strong>नोट:</strong> आसान खोज के लिए Shopify App Store पर भी उपलब्ध है।',
    
    'installation_guide' => 'इंस्टॉलेशन गाइड',
    'step' => 'चरण :number',
    
    'wordpress_install_steps' => [
        'प्लगइन ZIP फ़ाइल डाउनलोड करें',
        'WordPress व्यवस्थापक → प्लगइन्स → नया जोड़ें पर जाएं',
        '"प्लगइन अपलोड करें" पर क्लिक करें और डाउनलोड की गई ZIP चुनें',
        'प्लगइन को सक्रिय करें',
        'सेटिंग्स → AI Chat Support में अपनी API सेटिंग्स कॉन्फ़िगर करें'
    ],
    
    'magento_install_steps' => [
        'एक्सटेंशन ZIP फ़ाइल डाउनलोड करें',
        'सामग्री को अपनी Magento रूट डायरेक्टरी में निकालें',
        'चलाएं: php bin/magento setup:upgrade',
        'चलाएं: php bin/magento cache:flush',
        'स्टोर → कॉन्फ़िगरेशन → AI Chat Support में कॉन्फ़िगर करें'
    ],
    
    'shopify_install_steps' => [
        'ऊपर "Shopify ऐप इंस्टॉल करें" बटन पर क्लिक करें',
        'अपने Shopify व्यवस्थापक में ऐप को अधिकृत करें',
        'अपने संगठन स्लग और सेटिंग्स कॉन्फ़िगर करें',
        'चैट विजेट स्वचालित रूप से आपके स्टोर पर दिखाई देगा'
    ],
];
