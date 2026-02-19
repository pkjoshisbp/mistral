<?php
return [
    'page_title' => 'Integrationen',
    'page_subtitle' => 'Einfache Integration mit Ihren Lieblingsplattformen. Installieren Sie unser KI-Chat-Support-System auf WordPress, WooCommerce, Magento und Shopify mit nur wenigen Klicks.',
    
    'description' => 'Beschreibung',
    'features' => 'Funktionen:',
    'requirements' => 'Anforderungen:',
    
    'download_button' => ':platform Plugin herunterladen',
    'download_size' => ':size',
    'install_button' => ':platform App installieren',
    
    'wordpress_note' => '<strong>Hinweis:</strong> Sie finden dieses Plugin auch im offiziellen WordPress.org-Repository.',
    'magento_note' => '<strong>Hinweis:</strong> Laden Sie den ZIP-Inhalt in Ihr Magento-Root-Verzeichnis hoch.',
    'magento_composer_button' => 'Composer-Paket herunterladen',
    'shopify_note' => '<strong>Hinweis:</strong> Auch im Shopify App Store zur einfachen Entdeckung verfügbar.',
    
    'installation_guide' => 'Installationsanleitung',
    'step' => 'Schritt :number',
    
    'wordpress_install_steps' => [
        'Plugin-ZIP-Datei herunterladen',
        'Gehen Sie zu WordPress Admin → Plugins → Neu hinzufügen',
        'Klicken Sie auf "Plugin hochladen" und wählen Sie die heruntergeladene ZIP-Datei',
        'Plugin aktivieren',
        'Konfigurieren Sie Ihre API-Einstellungen unter Einstellungen → AI Chat Support'
    ],
    
    'magento_install_steps' => [
        'Erweiterungs-ZIP-Datei herunterladen',
        'Extrahieren Sie den Inhalt in Ihr Magento-Root-Verzeichnis',
        'Ausführen: php bin/magento setup:upgrade',
        'Ausführen: php bin/magento cache:flush',
        'Konfigurieren in Stores → Konfiguration → AI Chat Support'
    ],
    
    'shopify_install_steps' => [
        'Klicken Sie oben auf die Schaltfläche "Shopify App installieren"',
        'Autorisieren Sie die App in Ihrem Shopify-Admin',
        'Konfigurieren Sie Ihre Organisations-Slug und Einstellungen',
        'Das Chat-Widget erscheint automatisch in Ihrem Shop'
    ],
];
