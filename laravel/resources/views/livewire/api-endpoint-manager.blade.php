<div class="p-6">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-2xl font-bold mb-6">API Endpoint Manager</h2>

        @if (session()->has('message'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('message') }}
            </div>
        @endif

        <!-- Organization Selection -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Select Organization</label>
            <select wire:model.live="selectedOrgId" class="w-full p-2 border border-gray-300 rounded-md">
                <option value="">Choose Organization</option>
                @foreach($organizations as $org)
                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                @endforeach
            </select>
        </div>

        @if($organization)
            <!-- API Token Configuration -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h3 class="text-lg font-medium mb-4">API Authentication</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">API Token</label>
                        <input type="password" wire:model.defer="apiToken" class="w-full p-2 border border-gray-300 rounded-md" 
                               placeholder="Enter API token for this organization">
                    </div>
                    <div class="flex items-end">
                        <button wire:click="saveEndpoint" class="bg-blue-500 text-white px-4 py-2 rounded-md">
                            Save Token
                        </button>
                    </div>
                </div>
            </div>

            <!-- Existing Endpoints -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">Configured API Endpoints</h3>
                    <button wire:click="$set('showAddForm', true)" class="bg-green-500 text-white px-4 py-2 rounded-md">
                        Add New Endpoint
                    </button>
                </div>

                @if(count($endpoints) > 0)
                    <div class="space-y-4">
                        @foreach($endpoints as $key => $endpoint)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-lg">{{ $endpoint['name'] }}</h4>
                                        <p class="text-gray-600 text-sm mb-2">{{ $endpoint['description'] }}</p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <span class="font-medium">Method:</span> 
                                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">{{ $endpoint['method'] }}</span>
                                            </div>
                                            <div>
                                                <span class="font-medium">URL:</span> 
                                                <code class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $endpoint['url'] }}</code>
                                            </div>
                                        </div>
                                        
                                        @if(isset($endpoint['parameters']) && count($endpoint['parameters']) > 0)
                                            <div class="mt-3">
                                                <span class="font-medium text-sm">Parameters:</span>
                                                <div class="flex flex-wrap gap-2 mt-1">
                                                    @foreach($endpoint['parameters'] as $paramName => $paramConfig)
                                                        <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs">
                                                            {{ $paramName }}
                                                            @if($paramConfig['required']) <span class="text-red-500">*</span> @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="flex space-x-2 ml-4">
                                        <button wire:click="editEndpoint('{{ $key }}')" class="bg-yellow-500 text-white px-3 py-1 rounded text-sm">
                                            Edit
                                        </button>
                                        <button wire:click="testEndpoint('{{ $key }}')" class="bg-blue-500 text-white px-3 py-1 rounded text-sm">
                                            Test
                                        </button>
                                        <button wire:click="deleteEndpoint('{{ $key }}')" class="bg-red-500 text-white px-3 py-1 rounded text-sm"
                                                onclick="return confirm('Are you sure you want to delete this endpoint?')">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-8">No API endpoints configured yet.</p>
                @endif
            </div>

            <!-- Add/Edit Endpoint Form -->
            @if($showAddForm)
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium mb-4">{{ $endpointKey ? 'Edit' : 'Add New' }} API Endpoint</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Endpoint Key</label>
                            <input type="text" wire:model.defer="endpointKey" class="w-full p-2 border border-gray-300 rounded-md" 
                                   placeholder="e.g., order_status">
                            @error('endpointKey') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Display Name</label>
                            <input type="text" wire:model.defer="endpointName" class="w-full p-2 border border-gray-300 rounded-md" 
                                   placeholder="e.g., Order Status">
                            @error('endpointName') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea wire:model.defer="endpointDescription" class="w-full p-2 border border-gray-300 rounded-md" rows="2"
                                  placeholder="Describe what this endpoint does"></textarea>
                        @error('endpointDescription') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">API URL</label>
                            <input type="url" wire:model.defer="endpointUrl" class="w-full p-2 border border-gray-300 rounded-md" 
                                   placeholder="https://your-api.com/orders/{order_id}">
                            @error('endpointUrl') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Method</label>
                            <select wire:model.defer="endpointMethod" class="w-full p-2 border border-gray-300 rounded-md">
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </div>
                    </div>

                    <!-- Add Parameter Form -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <h5 class="text-sm font-medium mb-2">Add Parameter</h5>
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                            <input type="text" wire:model.defer="newParamName" class="p-2 border border-gray-300 rounded-md text-sm" 
                                   placeholder="Parameter name">
                            <select wire:model.defer="newParamType" class="p-2 border border-gray-300 rounded-md text-sm">
                                <option value="string">String</option>
                                <option value="number">Number</option>
                                <option value="email">Email</option>
                                <option value="phone">Phone</option>
                            </select>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model.defer="newParamRequired" class="mr-1">
                                <span class="text-sm">Required</span>
                            </label>
                            <input type="text" wire:model.defer="newParamDescription" class="p-2 border border-gray-300 rounded-md text-sm" 
                                   placeholder="Description">
                            <button wire:click="addParameter" class="bg-blue-500 text-white px-3 py-2 rounded-md text-sm">
                                Add
                            </button>
                        </div>
                        <div class="mt-2">
                            <input type="text" wire:model.defer="newParamPattern" class="w-full p-2 border border-gray-300 rounded-md text-sm" 
                                   placeholder="Extraction pattern (regex) - e.g., order[#\s]*(\d+)">
                        </div>
                    </div>

                    <div class="flex space-x-4">
                        <button wire:click="saveEndpoint" class="bg-green-500 text-white px-4 py-2 rounded-md">
                            {{ $endpointKey ? 'Update' : 'Save' }} Endpoint
                        </button>
                        <button wire:click="resetForm" class="bg-gray-500 text-white px-4 py-2 rounded-md">
                            Cancel
                        </button>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
