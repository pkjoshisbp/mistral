<?php

namespace App\Livewire\Public;

use Livewire\Component;
use Illuminate\Support\Facades\Storage;

class Integrations extends Component
{
    public $pluginFiles = [];
    
    public function mount()
    {
        // Get available plugin files from storage
        $this->pluginFiles = [
            'wordpress' => [
                'name' => 'AI Chat Support - WordPress Plugin',
                'version' => '1.0.1',
                'file' => 'ai-chat-support-v1.0.1.zip',
                'size' => file_exists(base_path('../plugins/wordpress/ai-chat-support-v1.0.1.zip')) ? 
                    $this->formatFileSize(filesize(base_path('../plugins/wordpress/ai-chat-support-v1.0.1.zip'))) : '~8KB',
                'description' => 'Easy installation WordPress plugin that adds AI Chat Support to any WordPress or WooCommerce site.',
                'requirements' => [
                    'WordPress 5.0 or higher',
                    'PHP 7.4 or higher',
                    'Internet connection for API calls'
                ],
                'features' => [
                    'One-click installation',
                    'Admin settings panel',
                    'Customizable widget position',
                    'Automatic updates',
                    'Secure API integration'
                ]
            ],
            'magento' => [
                'name' => 'AI Chat Support - Magento 2 Extension',
                'version' => '1.0.0',
                'file' => 'ai-chat-support-magento-1.0.0.zip',
                'size' => file_exists(base_path('../plugins/magento/ai-chat-support-magento-1.0.0.zip')) ?
                    $this->formatFileSize(filesize(base_path('../plugins/magento/ai-chat-support-magento-1.0.0.zip'))) : '~5KB',
                'composer_file' => 'ai-chat-support-magento-composer-1.0.0.zip',
                'composer_size' => file_exists(base_path('../plugins/magento/ai-chat-support-magento-composer-1.0.0.zip')) ?
                    $this->formatFileSize(filesize(base_path('../plugins/magento/ai-chat-support-magento-composer-1.0.0.zip'))) : '~6KB',
                'description' => 'Magento 2 extension that injects AI Chat Support on storefront pages with admin-configurable org slug/ID.',
                'requirements' => [
                    'Magento 2.3 or higher',
                    'Admin access to configuration',
                    'Internet connection for widget script'
                ],
                'features' => [
                    'Admin config (enable + org slug/ID)',
                    'No code changes needed',
                    'Works across store views',
                    'Lightweight and fast'
                ]
            ],
            'shopify' => [
                'name' => 'AI Chat Support - Shopify App',
                'version' => '1.0.0',
                'install_url' => 'https://ai-chat.support/shopify/install',
                'description' => 'Professional Shopify app that integrates AI Chat Support directly into your Shopify store.',
                'requirements' => [
                    'Active Shopify store',
                    'Admin permissions',
                    'Basic or higher Shopify plan'
                ],
                'features' => [
                    'OAuth secure installation',
                    'Theme integration',
                    'Mobile responsive',
                    'Analytics tracking',
                    'Customizable appearance'
                ]
            ]
        ];
    }
    
    private function formatFileSize($size)
    {
        if ($size >= 1024 * 1024) {
            return round($size / (1024 * 1024), 1) . ' MB';
        } elseif ($size >= 1024) {
            return round($size / 1024, 1) . ' KB';
        } else {
            return $size . ' bytes';
        }
    }
    
    public function downloadWordPress()
    {
        // Redirect to download route
        return $this->redirect(route('download.wordpress-plugin'));
    }

    public function downloadMagento()
    {
        return $this->redirect(route('download.magento-plugin'));
    }

    public function downloadMagentoComposer()
    {
        return $this->redirect(route('download.magento-composer-package'));
    }
    
    public function render()
    {
        return view('livewire.public.integrations')
            ->layout('layouts.public')
            ->title('Integrations - WordPress & Shopify Plugins');
    }
}