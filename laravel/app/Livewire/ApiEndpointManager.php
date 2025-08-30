<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;

class ApiEndpointManager extends Component
{
    public $selectedOrgId;
    public $organization;
    public $endpoints = [];
    public $apiToken = '';
    
    // Form fields for new endpoint
    public $showAddForm = false;
    public $endpointKey = '';
    public $endpointName = '';
    public $endpointDescription = '';
    public $endpointUrl = '';
    public $endpointMethod = 'GET';
    public $endpointParameters = [];
    public $newParamName = '';
    public $newParamType = 'string';
    public $newParamRequired = true;
    public $newParamDescription = '';
    public $newParamPattern = '';

    protected $rules = [
        'endpointKey' => 'required|string|max:50',
        'endpointName' => 'required|string|max:100',
        'endpointDescription' => 'required|string|max:500',
        'endpointUrl' => 'required|url',
        'endpointMethod' => 'required|in:GET,POST,PUT,DELETE',
        'apiToken' => 'nullable|string'
    ];

    public function mount()
    {
        $this->loadOrganizations();
    }

    public function loadOrganizations()
    {
        // Set default org if only one exists
        $orgs = Organization::all();
        if ($orgs->count() === 1) {
            $this->selectedOrgId = $orgs->first()->id;
            $this->updatedSelectedOrgId();
        }
    }

    public function updatedSelectedOrgId()
    {
        if ($this->selectedOrgId) {
            $this->organization = Organization::find($this->selectedOrgId);
            $this->endpoints = $this->organization->api_endpoints ?? [];
            $this->apiToken = $this->organization->api_token ?? '';
        }
    }

    public function addParameter()
    {
        if ($this->newParamName) {
            $this->endpointParameters[$this->newParamName] = [
                'type' => $this->newParamType,
                'required' => $this->newParamRequired,
                'description' => $this->newParamDescription,
                'extraction_patterns' => $this->newParamPattern ? [$this->newParamPattern] : []
            ];
            
            $this->reset(['newParamName', 'newParamType', 'newParamDescription', 'newParamPattern']);
            $this->newParamRequired = true;
        }
    }

    public function removeParameter($paramName)
    {
        unset($this->endpointParameters[$paramName]);
    }

    public function saveEndpoint()
    {
        $this->validate();

        if (!$this->organization) return;

        $endpoints = $this->organization->api_endpoints ?? [];
        
        $endpoints[$this->endpointKey] = [
            'name' => $this->endpointName,
            'description' => $this->endpointDescription,
            'url' => $this->endpointUrl,
            'method' => $this->endpointMethod,
            'headers' => [
                'Authorization' => 'Bearer {api_token}',
                'Content-Type' => 'application/json'
            ],
            'parameters' => $this->endpointParameters,
            'response_format' => 'json',
            'enabled' => true
        ];

        $this->organization->update([
            'api_endpoints' => $endpoints,
            'api_token' => $this->apiToken
        ]);

        $this->endpoints = $endpoints;
        $this->resetForm();
        session()->flash('message', 'API endpoint saved successfully!');
    }

    public function editEndpoint($key)
    {
        $endpoint = $this->endpoints[$key] ?? null;
        if (!$endpoint) return;

        $this->endpointKey = $key;
        $this->endpointName = $endpoint['name'];
        $this->endpointDescription = $endpoint['description'];
        $this->endpointUrl = $endpoint['url'];
        $this->endpointMethod = $endpoint['method'];
        $this->endpointParameters = $endpoint['parameters'] ?? [];
        $this->showAddForm = true;
    }

    public function deleteEndpoint($key)
    {
        $endpoints = $this->organization->api_endpoints ?? [];
        unset($endpoints[$key]);
        
        $this->organization->update(['api_endpoints' => $endpoints]);
        $this->endpoints = $endpoints;
        session()->flash('message', 'API endpoint deleted successfully!');
    }

    public function testEndpoint($key)
    {
        // TODO: Implement endpoint testing functionality
        session()->flash('message', 'Endpoint test functionality coming soon!');
    }

    public function resetForm()
    {
        $this->reset([
            'showAddForm', 'endpointKey', 'endpointName', 'endpointDescription',
            'endpointUrl', 'endpointMethod', 'endpointParameters'
        ]);
    }

    public function render()
    {
        return view('livewire.api-endpoint-manager', [
            'organizations' => Organization::all()
        ]);
    }
}
