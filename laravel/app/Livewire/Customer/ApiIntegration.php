<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ApiIntegration extends Component
{
    public $apiKey = '';
    public $endpoints = [];
    public $showApiKey = false;

    public function mount()
    {
        $this->loadApiKey();
        $this->loadEndpoints();
    }

    public function loadApiKey()
    {
        $user = Auth::user();
        $org = $user->primaryOrganization();
        if ($org) {
            $this->apiKey = $org->api_key ?? 'Not generated';
        }
    }

    public function generateApiKey()
    {
    $organization = Auth::user()->primaryOrganization();
        if (!$organization) {
            session()->flash('error', 'No organization assigned to your account.');
            return;
        }

        $newApiKey = 'ac_' . Str::random(32);
        $organization->update(['api_key' => $newApiKey]);
        $this->apiKey = $newApiKey;
        
        session()->flash('message', 'New API key generated successfully!');
    }

    public function toggleApiKeyVisibility()
    {
        $this->showApiKey = !$this->showApiKey;
    }

    public function loadEndpoints()
    {
    $organization = Auth::user()->primaryOrganization();
        $baseUrl = config('app.url');
        $orgSlug = $organization ? $organization->slug : 'your-org-slug';

        $this->endpoints = [
            [
                'name' => 'Chat Completion',
                'method' => 'POST',
                'url' => "{$baseUrl}/api/v1/chat",
                'description' => 'Send a message and get AI response',
                'example' => [
                    'message' => 'Hello, I need help with my order',
                    'session_id' => 'unique-session-id',
                    'user_info' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com'
                    ]
                ]
            ],
            [
                'name' => 'Add Knowledge',
                'method' => 'POST',
                'url' => "{$baseUrl}/api/v1/knowledge",
                'description' => 'Add content to your knowledge base',
                'example' => [
                    'title' => 'FAQ: How to return items',
                    'content' => 'You can return items within 30 days...',
                    'category' => 'returns',
                    'metadata' => [
                        'source' => 'api',
                        'created_by' => 'system'
                    ]
                ]
            ],
            [
                'name' => 'Get Chat History',
                'method' => 'GET',
                'url' => "{$baseUrl}/api/v1/chat/history",
                'description' => 'Retrieve chat conversation history',
                'example' => [
                    'session_id' => 'unique-session-id',
                    'limit' => 50,
                    'offset' => 0
                ]
            ]
        ];
    }

    public function copyToClipboard($text)
    {
        session()->flash('message', 'Copied to clipboard!');
    }

    public function render()
    {
        return view('livewire.customer.api-integration')
            ->layout('layouts.customer')
            ->layoutData(['title' => 'API Integration']);
    }
}
