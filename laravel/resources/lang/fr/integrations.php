<?php
return [
    'page_title' => 'Intégrations',
    'page_subtitle' => 'Intégration facile avec vos plateformes préférées. Installez notre système de support par chat IA sur WordPress, WooCommerce, Magento et Shopify en quelques clics.',
    
    'description' => 'Description',
    'features' => 'Fonctionnalités:',
    'requirements' => 'Exigences:',
    
    'download_button' => 'Télécharger le plugin :platform',
    'download_size' => ':size',
    'install_button' => 'Installer l\'application :platform',
    
    'wordpress_note' => '<strong>Remarque:</strong> Vous pouvez également trouver ce plugin sur le référentiel officiel WordPress.org.',
    'magento_note' => '<strong>Remarque:</strong> Téléchargez le contenu du ZIP dans votre répertoire racine Magento.',
    'magento_composer_button' => 'Télécharger le package Composer',
    'shopify_note' => '<strong>Remarque:</strong> Également disponible sur le Shopify App Store pour une découverte facile.',
    
    'installation_guide' => 'Guide d\'installation',
    'step' => 'Étape :number',
    
    'wordpress_install_steps' => [
        'Téléchargez le fichier ZIP du plugin',
        'Allez dans Administration WordPress → Extensions → Ajouter',
        'Cliquez sur "Téléverser une extension" et sélectionnez le ZIP téléchargé',
        'Activez le plugin',
        'Configurez vos paramètres API dans Réglages → AI Chat Support'
    ],
    
    'magento_install_steps' => [
        'Téléchargez le fichier ZIP de l\'extension',
        'Extrayez le contenu dans votre répertoire racine Magento',
        'Exécutez: php bin/magento setup:upgrade',
        'Exécutez: php bin/magento cache:flush',
        'Configurez dans Magasins → Configuration → AI Chat Support'
    ],
    
    'shopify_install_steps' => [
        'Cliquez sur le bouton "Installer l\'application Shopify" ci-dessus',
        'Autorisez l\'application dans votre administration Shopify',
        'Configurez votre identifiant d\'organisation et vos paramètres',
        'Le widget de chat apparaîtra automatiquement sur votre boutique'
    ],
];
