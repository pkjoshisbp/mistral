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
    
    public function render()
    {
        return view('livewire.public.integrations')
            ->layout('layouts.public')
            ->title('Integrations - WordPress & Shopify Plugins');
    }
}