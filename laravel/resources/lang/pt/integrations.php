<?php
return [
    'page_title' => 'Integrações',
    'page_subtitle' => 'Integração fácil com suas plataformas favoritas. Instale nosso sistema de suporte por chat IA no WordPress, WooCommerce, Magento e Shopify com apenas alguns cliques.',
    
    'description' => 'Descrição',
    'features' => 'Recursos:',
    'requirements' => 'Requisitos:',
    
    'download_button' => 'Baixar plugin :platform',
    'download_size' => ':size',
    'install_button' => 'Instalar aplicativo :platform',
    
    'wordpress_note' => '<strong>Nota:</strong> Você também pode encontrar este plugin no repositório oficial do WordPress.org.',
    'magento_note' => '<strong>Nota:</strong> Faça upload do conteúdo do ZIP para o diretório raiz do Magento.',
    'magento_composer_button' => 'Baixar pacote Composer',
    'shopify_note' => '<strong>Nota:</strong> Também disponível na Shopify App Store para fácil descoberta.',
    
    'installation_guide' => 'Guia de instalação',
    'step' => 'Passo :number',
    
    'wordpress_install_steps' => [
        'Baixe o arquivo ZIP do plugin',
        'Vá para Administração WordPress → Plugins → Adicionar novo',
        'Clique em "Enviar plugin" e selecione o ZIP baixado',
        'Ative o plugin',
        'Configure suas configurações de API em Configurações → AI Chat Support'
    ],
    
    'magento_install_steps' => [
        'Baixe o arquivo ZIP da extensão',
        'Extraia o conteúdo para o diretório raiz do Magento',
        'Execute: php bin/magento setup:upgrade',
        'Execute: php bin/magento cache:flush',
        'Configure em Lojas → Configuração → AI Chat Support'
    ],
    
    'shopify_install_steps' => [
        'Clique no botão "Instalar aplicativo Shopify" acima',
        'Autorize o aplicativo na sua administração Shopify',
        'Configure o slug da sua organização e configurações',
        'O widget de chat aparecerá automaticamente na sua loja'
    ],
];
