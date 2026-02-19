<?php
return [
    'page_title' => 'Integraciones',
    'page_subtitle' => 'Integración fácil con tus plataformas favoritas. Instala nuestro sistema de soporte por chat IA en WordPress, WooCommerce, Magento y Shopify con solo unos clics.',
    
    'description' => 'Descripción',
    'features' => 'Características:',
    'requirements' => 'Requisitos:',
    
    'download_button' => 'Descargar plugin de :platform',
    'download_size' => ':size',
    'install_button' => 'Instalar aplicación :platform',
    
    'wordpress_note' => '<strong>Nota:</strong> También puedes encontrar este plugin en el repositorio oficial de WordPress.org.',
    'magento_note' => '<strong>Nota:</strong> Sube el contenido del ZIP a tu directorio raíz de Magento.',
    'magento_composer_button' => 'Descargar paquete Composer',
    'shopify_note' => '<strong>Nota:</strong> También disponible en Shopify App Store para facilitar su descubrimiento.',
    
    'installation_guide' => 'Guía de instalación',
    'step' => 'Paso :number',
    
    'wordpress_install_steps' => [
        'Descarga el archivo ZIP del plugin',
        'Ve a Administración de WordPress → Plugins → Añadir nuevo',
        'Haz clic en "Subir plugin" y selecciona el ZIP descargado',
        'Activa el plugin',
        'Configura tus ajustes de API en Ajustes → AI Chat Support'
    ],
    
    'magento_install_steps' => [
        'Descarga el archivo ZIP de la extensión',
        'Extrae el contenido en tu directorio raíz de Magento',
        'Ejecuta: php bin/magento setup:upgrade',
        'Ejecuta: php bin/magento cache:flush',
        'Configura en Tiendas → Configuración → AI Chat Support'
    ],
    
    'shopify_install_steps' => [
        'Haz clic en el botón "Instalar aplicación Shopify" arriba',
        'Autoriza la aplicación en tu administración de Shopify',
        'Configura tu slug de organización y ajustes',
        'El widget de chat aparecerá automáticamente en tu tienda'
    ],
];
