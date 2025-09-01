<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">API Integration</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">API Integration</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- API Key Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-key mr-2"></i>
                    API Authentication
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Your API Key</label>
                            <div class="input-group">
                                <input type="{{ $showApiKey ? 'text' : 'password' }}" 
                                       class="form-control" 
                                       value="{{ $apiKey }}" 
                                       readonly>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" 
                                            type="button" 
                                            wire:click="toggleApiKeyVisibility">
                                        <i class="fas fa-{{ $showApiKey ? 'eye-slash' : 'eye' }}"></i>
                                    </button>
                                    <button class="btn btn-outline-primary" 
                                            type="button" 
                                            onclick="copyToClipboard('{{ $apiKey }}')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted">
                                Use this API key in the <code>Authorization: Bearer YOUR_API_KEY</code> header
                            </small>
                        </div>
                        <button type="button" 
                                class="btn btn-warning" 
                                wire:click="generateApiKey"
                                onclick="return confirm('This will invalidate your current API key. Continue?')">
                            <i class="fas fa-sync"></i> Generate New API Key
                        </button>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle"></i> Security Notice:</h6>
                            <ul class="mb-0">
                                <li>Keep your API key secure</li>
                                <li>Don't share it publicly</li>
                                <li>Use HTTPS for all requests</li>
                                <li>Regenerate if compromised</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Endpoints -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-plug mr-2"></i>
                    Available Endpoints
                </h3>
            </div>
            <div class="card-body">
                @foreach($endpoints as $index => $endpoint)
                    <div class="card mb-3 {{ $index === 0 ? 'border-primary' : '' }}">
                        <div class="card-header {{ $index === 0 ? 'bg-primary text-white' : '' }}">
                            <h5 class="mb-0">
                                <span class="badge badge-{{ $endpoint['method'] === 'POST' ? 'success' : 'info' }} mr-2">
                                    {{ $endpoint['method'] }}
                                </span>
                                {{ $endpoint['name'] }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">{{ $endpoint['description'] }}</p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Endpoint URL:</h6>
                                    <div class="input-group mb-3">
                                        <input type="text" 
                                               class="form-control" 
                                               value="{{ $endpoint['url'] }}" 
                                               readonly>
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-primary" 
                                                    type="button" 
                                                    onclick="copyToClipboard('{{ $endpoint['url'] }}')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Example Request:</h6>
                                    <pre class="bg-light p-3 rounded"><code>{{ json_encode($endpoint['example'], JSON_PRETTY_PRINT) }}</code></pre>
                                </div>
                            </div>

                            @if($index === 0)
                                <div class="mt-3">
                                    <h6>cURL Example:</h6>
                                    <pre class="bg-dark text-white p-3 rounded"><code>curl -X POST {{ $endpoint['url'] }} \
  -H "Authorization: Bearer {{ $apiKey }}" \
  -H "Content-Type: application/json" \
  -d '{{ json_encode($endpoint['example']) }}'</code></pre>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Code Examples -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-code mr-2"></i>
                    Integration Examples
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>JavaScript/Node.js Example:</h6>
                        <pre class="bg-light p-3 rounded"><code>// Using fetch API
const response = await fetch('{{ $endpoints[0]['url'] }}', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer {{ $apiKey }}',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    message: 'Hello, I need help',
    session_id: 'unique-session-' + Date.now(),
    user_info: {
      name: 'Customer Name',
      email: 'customer@example.com'
    }
  })
});

const data = await response.json();
console.log(data.response);</code></pre>
                    </div>
                    <div class="col-md-6">
                        <h6>PHP Example:</h6>
                        <pre class="bg-light p-3 rounded"><code>// Using cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, '{{ $endpoints[0]['url'] }}');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer {{ $apiKey }}',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'message' => 'Hello, I need help',
    'session_id' => 'unique-session-' . time(),
    'user_info' => [
        'name' => 'Customer Name',
        'email' => 'customer@example.com'
    ]
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$data = json_decode($response, true);
echo $data['response'];</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // You could show a toast notification here
        console.log('Copied to clipboard');
    });
}
</script>
