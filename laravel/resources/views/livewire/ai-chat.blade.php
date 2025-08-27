<div class="card">
    <div class="card-header">
        <h3 class="card-title">AI Chat Interface</h3>
        <div class="card-tools">
            <button wire:click="clearChat" class="btn btn-warning btn-sm">
                <i class="fas fa-trash"></i> Clear Chat
            </button>
        </div>
    </div>

    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="selectedOrgId">Select Organization</label>
                    <select wire:model.live="selectedOrgId" class="form-control" id="selectedOrgId">
                        <option value="">Choose an organization...</option>
                        @foreach ($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="chat-box" style="height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; background-color: #f8f9fa;">
            @forelse ($messages as $message)
                <div class="message mb-3 {{ $message['role'] === 'user' ? 'text-right' : 'text-left' }}">
                    <div class="d-inline-block p-2 rounded {{ $message['role'] === 'user' ? 'bg-primary text-white' : ($message['role'] === 'system' ? 'bg-danger text-white' : 'bg-light') }}" style="max-width: 70%;">
                        <strong>{{ ucfirst($message['role']) }}:</strong><br>
                        {{ $message['content'] }}
                        <br><small class="text-muted">{{ $message['timestamp']->format('H:i:s') }}</small>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted">
                    <i class="fas fa-comments fa-3x mb-3"></i>
                    <p>No messages yet. Start a conversation!</p>
                </div>
            @endforelse

            @if ($isLoading)
                <div class="message mb-3 text-left">
                    <div class="d-inline-block p-3 rounded bg-light border">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <span class="text-muted">AI is analyzing your question, please wait...</span>
                        </div>
                    </div>
                </div>
            @endif
            
            <!-- Real-time loading indicator -->
            <div wire:loading.delay wire:target="sendMessage" class="message mb-3 text-left">
                <div class="d-inline-block p-3 rounded bg-light border">
                    <div class="d-flex align-items-center">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <span class="text-muted">Processing your request...</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="input-group">
            <input type="text" wire:model.live="query" wire:keydown.enter="sendMessage" class="form-control" placeholder="Type your message..." {{ !$selectedOrgId || $isLoading ? 'disabled' : '' }}>
            <div class="input-group-append">
                <button wire:click="sendMessage" class="btn btn-primary" {{ !$selectedOrgId || $isLoading ? 'disabled' : '' }}>
                    <span wire:loading.remove wire:target="sendMessage">
                        <i class="fas fa-paper-plane"></i> Send
                    </span>
                    <span wire:loading wire:target="sendMessage">
                        <i class="fas fa-spinner fa-spin"></i> Sending...
                    </span>
                </button>
            </div>
        </div>

        @if (!$selectedOrgId)
            <small class="text-muted">Please select an organization to start chatting.</small>
        @endif
    </div>
</div>

<script>
    // Auto-scroll to bottom when new messages are added
    document.addEventListener('livewire:load', function () {
        Livewire.hook('message.processed', (message, component) => {
            const chatBox = document.querySelector('.chat-box');
            if (chatBox) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        });
    });
</script>
