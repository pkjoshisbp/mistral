@extends('layouts.customer')

@section('title', 'Chat Test')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Test Your AI Chat</h4>
                    <p class="text-muted mb-0">Test your AI chat functionality before deploying to your website</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="chat-test-container" style="border: 1px solid #ddd; border-radius: 8px; height: 500px; display: flex; flex-direction: column;">
                                <div class="chat-header" style="background: #007bff; color: white; padding: 15px; border-radius: 8px 8px 0 0;">
                                    <h6 class="mb-0">AI Chat Test</h6>
                                </div>
                                <div class="chat-messages" id="test-messages" style="flex: 1; padding: 15px; overflow-y: auto;">
                                    <div class="message bot-message" style="margin-bottom: 15px;">
                                        <div style="background: #f1f3f4; padding: 10px; border-radius: 15px; max-width: 80%; display: inline-block;">
                                            Hello! I'm your AI assistant. How can I help you today?
                                        </div>
                                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                            {{ now()->format('g:i A') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="chat-input" style="padding: 15px; border-top: 1px solid #ddd;">
                                    <div class="input-group">
                                        <input type="text" id="test-message-input" class="form-control" placeholder="Type your message...">
                                        <button class="btn btn-primary" onclick="sendTestMessage()">Send</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Test Information</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Organization ID:</strong> {{ auth()->user()->organization_id ?? 3 }}</p>
                                    <p><strong>API Endpoint:</strong> <code>/widget/{{ auth()->user()->organization_id ?? 3 }}/chat</code></p>
                                    <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                                    <hr>
                                    <h6>Test Features:</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> Real AI responses</li>
                                        <li><i class="fas fa-check text-success"></i> Organization data</li>
                                        <li><i class="fas fa-check text-success"></i> Response timing</li>
                                        <li><i class="fas fa-check text-success"></i> Error handling</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">Quick Test Questions</h6>
                                </div>
                                <div class="card-body">
                                    <button class="btn btn-outline-primary btn-sm mb-2 w-100" onclick="quickTest('What are your pricing plans?')">Pricing Info</button>
                                    <button class="btn btn-outline-primary btn-sm mb-2 w-100" onclick="quickTest('How can I contact support?')">Contact Info</button>
                                    <button class="btn btn-outline-primary btn-sm mb-2 w-100" onclick="quickTest('What services do you offer?')">Services</button>
                                    <button class="btn btn-outline-primary btn-sm w-100" onclick="quickTest('Tell me about your company')">About Company</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let sessionId = 'test_' + Math.random().toString(36).substr(2, 9);

function addTestMessage(message, sender) {
    const messagesContainer = document.getElementById('test-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message ' + sender + '-message';
    messageDiv.style.marginBottom = '15px';
    
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    if (sender === 'user') {
        messageDiv.innerHTML = `
            <div style="text-align: right;">
                <div style="background: #007bff; color: white; padding: 10px; border-radius: 15px; max-width: 80%; display: inline-block;">
                    ${message}
                </div>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                    ${time}
                </div>
            </div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div style="background: #f1f3f4; padding: 10px; border-radius: 15px; max-width: 80%; display: inline-block;">
                ${message}
            </div>
            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                ${time}
            </div>
        `;
    }
    
    messagesContainer.appendChild(messageDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function showTypingIndicator() {
    const messagesContainer = document.getElementById('test-messages');
    const typingDiv = document.createElement('div');
    typingDiv.id = 'typing-indicator';
    typingDiv.className = 'message bot-message';
    typingDiv.style.marginBottom = '15px';
    typingDiv.innerHTML = `
        <div style="background: #f1f3f4; padding: 10px; border-radius: 15px; max-width: 80%; display: inline-block;">
            <span style="animation: pulse 1.5s infinite;">AI is typing...</span>
        </div>
    `;
    messagesContainer.appendChild(typingDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function removeTypingIndicator() {
    const typing = document.getElementById('typing-indicator');
    if (typing) {
        typing.remove();
    }
}

async function sendTestMessage() {
    const input = document.getElementById('test-message-input');
    const message = input.value.trim();
    
    if (!message) return;
    
    addTestMessage(message, 'user');
    input.value = '';
    
    showTypingIndicator();
    
    try {
        const response = await fetch('/widget/{{ auth()->user()->organization_id ?? 3 }}/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                message: message,
                session_id: sessionId
            })
        });
        
        const data = await response.json();
        removeTypingIndicator();
        
        if (data.response) {
            addTestMessage(data.response, 'bot');
        } else {
            addTestMessage('Sorry, I encountered an error. Please try again.', 'bot');
        }
    } catch (error) {
        console.error('Test chat error:', error);
        removeTypingIndicator();
        addTestMessage('Connection error. Please check your internet connection.', 'bot');
    }
}

function quickTest(question) {
    document.getElementById('test-message-input').value = question;
    sendTestMessage();
}

// Allow Enter key to send message
document.getElementById('test-message-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        sendTestMessage();
    }
});
</script>

<style>
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
</style>
@endsection
