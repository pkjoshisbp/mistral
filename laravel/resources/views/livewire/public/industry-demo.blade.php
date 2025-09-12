<div class="industry-demo-container">
    <!-- Hero Section -->
    <div class="hero-section bg-gradient-to-r from-blue-600 to-purple-600 text-white py-16">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">{{ $selectedDemo['title'] }}</h1>
            <p class="text-xl md:text-2xl mb-6">{{ $selectedDemo['subtitle'] }}</p>
            <div class="bg-white/20 backdrop-blur-sm rounded-lg p-6 max-w-2xl mx-auto">
                <p class="text-lg">{{ $selectedDemo['description'] }}</p>
            </div>
        </div>
    </div>

    <!-- Demo Section -->
    <div class="container mx-auto px-4 py-12">
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Features Panel -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4 text-gray-800">🚀 Key Features</h3>
                    <ul class="space-y-2">
                        @foreach($selectedDemo['features'] as $feature)
                            <li class="flex items-center text-gray-700">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold mb-4 text-gray-800">💬 Try These Questions</h3>
                    <div class="space-y-2">
                        @foreach($selectedDemo['sample_questions'] as $question)
                            <button wire:click="sendSampleQuestion('{{ $question }}')" 
                                    class="w-full text-left p-3 bg-gray-50 hover:bg-blue-50 rounded-lg border border-gray-200 hover:border-blue-300 transition-colors text-sm">
                                "{{ $question }}"
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Chat Interface -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <!-- Chat Header -->
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-robot text-lg"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold">{{ $selectedDemo['organization'] }}</h3>
                                <p class="text-sm opacity-90">AI Assistant • Online</p>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Messages -->
                    <div class="h-96 overflow-y-auto p-4 bg-gray-50" id="chatMessages" wire:key="chat-messages-{{ count($messages) }}">
                        @foreach($messages as $index => $message)
                            <div class="mb-4 {{ $message['role'] === 'user' ? 'text-right' : 'text-left' }}" wire:key="message-{{ $index }}-{{ $message['timestamp']->timestamp }}">
                                <div class="inline-block max-w-xs lg:max-w-md">
                                    <div class="p-3 rounded-lg {{ $message['role'] === 'user' 
                                        ? 'bg-blue-500 text-white rounded-br-none' 
                                        : 'bg-white border border-gray-200 rounded-bl-none' }}">
                                        {!! nl2br(e($message['content'])) !!}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1 {{ $message['role'] === 'user' ? 'text-right' : 'text-left' }}">
                                        {{ $message['timestamp']->format('H:i:s') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        @if($isLoading)
                        <div class="mb-4 text-left" wire:key="typing-indicator">
                            <div class="inline-block max-w-xs lg:max-w-md">
                                <div class="p-3 bg-white border border-gray-200 rounded-lg rounded-bl-none">
                                    <div class="typing-indicator">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        <!-- Real-time loading indicator -->
                        <div wire:loading.delay wire:target="sendMessage" class="mb-4 text-left">
                            <div class="inline-block max-w-xs lg:max-w-md">
                                <div class="p-3 bg-white border border-gray-200 rounded-lg rounded-bl-none">
                                    <div class="typing-indicator">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Input -->
                    <div class="p-4 border-t border-gray-200">
                        <div class="flex space-x-2">
                            <input type="text" 
                                   wire:model.live="query" 
                                   wire:keydown.enter="sendMessage"
                                   placeholder="Type your message here..." 
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   {{ $isLoading ? 'disabled' : '' }}>
                            <button wire:click="sendMessage" 
                                    class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                                    {{ $isLoading ? 'disabled' : '' }}>
                                <span wire:loading.remove wire:target="sendMessage">
                                    <i class="fas fa-paper-plane"></i>
                                </span>
                                <span wire:loading wire:target="sendMessage">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Industry Selection -->
        <div class="mt-12 text-center">
            <h3 class="text-2xl font-bold mb-6 text-gray-800">Try Other Industry Demos</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                @php
                $industries = [
                    'healthcare' => ['name' => 'Healthcare', 'icon' => 'fa-heartbeat', 'color' => 'red'],
                    'education' => ['name' => 'Education', 'icon' => 'fa-graduation-cap', 'color' => 'blue'],
                    'automotive' => ['name' => 'Automotive', 'icon' => 'fa-car', 'color' => 'green'],
                    'ecommerce' => ['name' => 'E-commerce', 'icon' => 'fa-shopping-cart', 'color' => 'purple'],
                    'hospitality' => ['name' => 'Hotels', 'icon' => 'fa-bed', 'color' => 'pink'],
                    'realestate' => ['name' => 'Real Estate', 'icon' => 'fa-home', 'color' => 'yellow']
                ];
                @endphp

                @foreach($industries as $key => $info)
                    <a href="{{ route('demo', ['industry' => $key]) }}" 
                       class="p-4 rounded-lg border-2 transition-all hover:shadow-lg {{ $industry === $key 
                        ? 'border-blue-500 bg-blue-50' 
                        : 'border-gray-200 hover:border-gray-300' }}">
                        <div class="text-center">
                            <i class="fas {{ $info['icon'] }} text-2xl mb-2 text-{{ $info['color'] }}-500"></i>
                            <p class="font-semibold text-sm">{{ $info['name'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- CTA Section -->
        <div class="mt-16 text-center bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg p-8">
            <h3 class="text-3xl font-bold mb-4">Ready to Transform Your Business?</h3>
            <p class="text-xl mb-6">See how AI Chat Support can work for your industry</p>
            <div class="space-x-4">
                <a href="{{ route('contact') }}" class="inline-block bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    Get Started Today
                </a>
                <a href="https://ai-chat.support" class="inline-block border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition-colors">
                    Learn More
                </a>
            </div>
        </div>
    </div>

    <style>
    .typing-indicator {
        display: flex;
        align-items: center;
        gap: 2px;
    }

    .typing-indicator span {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #9CA3AF;
        animation: typing-bounce 1.5s infinite ease-in-out;
    }

    .typing-indicator span:nth-child(1) {
        animation-delay: 0s;
    }

    .typing-indicator span:nth-child(2) {
        animation-delay: 0.15s;
    }

    .typing-indicator span:nth-child(3) {
        animation-delay: 0.3s;
    }

    @keyframes typing-bounce {
        0%, 60%, 100% {
            transform: translateY(0);
            opacity: 0.4;
        }
        30% {
            transform: translateY(-8px);
            opacity: 1;
        }
    }

    /* Auto-scroll chat to bottom */
    #chatMessages {
        scroll-behavior: smooth;
    }
    </style>

    <script>
    document.addEventListener('livewire:load', function () {
        // Auto-scroll chat to bottom when new messages arrive
        const chatContainer = document.getElementById('chatMessages');
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    });

    document.addEventListener('livewire:update', function () {
        // Auto-scroll chat to bottom when new messages arrive
        setTimeout(() => {
            const chatContainer = document.getElementById('chatMessages');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        }, 50);
    });
    
    // Also scroll immediately when user sends a message
    document.addEventListener('livewire:component:update', function () {
        setTimeout(() => {
            const chatContainer = document.getElementById('chatMessages');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        }, 10);
    });
    </script>
</div>
