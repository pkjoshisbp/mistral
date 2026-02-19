<?php
return [
    'page_title' => 'Integrazioni',
    'page_subtitle' => 'Integrazione facile con le tue piattaforme preferite. Installa il nostro sistema di supporto chat IA su WordPress, WooCommerce, Magento e Shopify con pochi clic.',
    
    'description' => 'Descrizione',
    'features' => 'Funzionalità:',
    'requirements' => 'Requisiti:',
    
    'download_button' => 'Scarica plugin :platform',
    'download_size' => ':size',
    'install_button' => 'Installa app :platform',
    
    'wordpress_note' => '<strong>Nota:</strong> Puoi anche trovare questo plugin nel repository ufficiale di WordPress.org.',
    'magento_note' => '<strong>Nota:</strong> Carica il contenuto dello ZIP nella directory principale di Magento.',
    'magento_composer_button' => 'Scarica pacchetto Composer',
    'shopify_note' => '<strong>Nota:</strong> Disponibile anche su Shopify App Store per una facile scoperta.',
    
    'installation_guide' => 'Guida all\'installazione',
    'step' => 'Passo :number',
    
    'wordpress_install_steps' => [
        'Scarica il file ZIP del plugin',
        'Vai su Amministrazione WordPress → Plugin → Aggiungi nuovo',
        'Clicca su "Carica plugin" e seleziona lo ZIP scaricato',
        'Attiva il plugin',
        'Configura le impostazioni API in Impostazioni → AI Chat Support'
    ],
    
    'magento_install_steps' => [
        'Scarica il file ZIP dell\'estensione',
        'Estrai il contenuto nella directory principale di Magento',
        'Esegui: php bin/magento setup:upgrade',
        'Esegui: php bin/magento cache:flush',
        'Configura in Negozi → Configurazione → AI Chat Support'
    ],
    
    'shopify_install_steps' => [
        'Clicca sul pulsante "Installa app Shopify" sopra',
        'Autorizza l\'app nella tua amministrazione Shopify',
        'Configura lo slug della tua organizzazione e le impostazioni',
        'Il widget di chat apparirà automaticamente nel tuo negozio'
    ],
];
