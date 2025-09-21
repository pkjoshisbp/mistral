<div class="industry-demo-container">
    <!-- Hero Section -->
    <section class="py-5 text-white" style="background: linear-gradient(90deg, #2563eb, #7c3aed);">
        <div class="container text-center">
            <h1 class="display-5 fw-bold mb-3">{{ $selectedDemo['title'] }}</h1>
            <p class="lead mb-4">{{ $selectedDemo['subtitle'] }}</p>
            <div class="mx-auto" style="max-width: 720px;">
                <div class="card bg-transparent border-0">
                    <div class="card-body rounded-3" style="background: rgba(255,255,255,0.25);">
                        <p class="fs-5 mb-0">{{ $selectedDemo['description'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Demo Section -->
    <div class="container py-5">
        <div class="row g-4">
            <!-- Features Panel -->
            <div class="col-lg-4">
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5 fw-bold mb-3 text-dark">🚀 Key Features</h3>
                        <ul class="list-unstyled mb-0">
                            @foreach($selectedDemo['features'] as $feature)
                                <li class="d-flex align-items-start text-muted mb-2">
                                    <i class="fa-solid fa-circle-check text-success me-2 mt-1"></i>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="h5 fw-bold mb-3 text-dark">💬 Try These Questions</h3>
                        <div class="d-grid gap-2">
                            @foreach($selectedDemo['sample_questions'] as $question)
                                <button type="button" wire:click="sendSampleQuestion('{{ $question }}')" class="btn btn-outline-secondary text-start">
                                    "{{ $question }}"
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat Interface -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <!-- Chat Header -->
                    <div class="card-header text-white" style="background: linear-gradient(90deg, #3b82f6, #2563eb);">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;background: rgba(255,255,255,0.2);">
                                <i class="fa-solid fa-robot"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">{{ $selectedDemo['organization'] }}</div>
                                <small class="opacity-75">AI Assistant • Online</small>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Messages -->
                    <div class="card-body bg-light" style="height: 24rem; overflow-y: auto;" id="chatMessages" wire:key="chat-messages-{{ count($messages) }}">
                        @foreach($messages as $index => $message)
                            <div class="mb-3" wire:key="message-{{ $index }}-{{ $message['timestamp']->timestamp }}">
                                <div class="d-flex {{ $message['role'] === 'user' ? 'justify-content-end' : 'justify-content-start' }}">
                                    <div class="p-3 rounded-3 {{ $message['role'] === 'user' ? 'bg-primary text-white' : 'bg-white border' }}" style="max-width: 28rem;">
                                        {!! nl2br(e($message['content'])) !!}
                                    </div>
                                </div>
                                <div class="small text-muted {{ $message['role'] === 'user' ? 'text-end' : '' }} mt-1">
                                    {{ $message['timestamp']->format('H:i:s') }}
                                </div>
                            </div>
                        @endforeach

                        @if($isLoading)
                        <div class="mb-3">
                            <div class="d-flex justify-content-start">
                                <div class="p-3 bg-white border rounded-3">
                                    <div class="typing-indicator d-flex align-items-center gap-1">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Real-time loading indicator -->
                        <div wire:loading.delay wire:target="sendMessage" class="mb-3">
                            <div class="d-flex justify-content-start">
                                <div class="p-3 bg-white border rounded-3">
                                    <div class="typing-indicator d-flex align-items-center gap-1">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Input -->
                    <div class="card-footer bg-white">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Type your message here..." wire:model.live="query" wire:keydown.enter="sendMessage" {{ $isLoading ? 'disabled' : '' }}>
                            <button class="btn btn-primary" type="button" wire:click="sendMessage" {{ $isLoading ? 'disabled' : '' }}>
                                <span wire:loading.remove wire:target="sendMessage"><i class="fa-solid fa-paper-plane"></i></span>
                                <span wire:loading wire:target="sendMessage"><i class="fa-solid fa-spinner fa-spin"></i></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Industry Selection -->
        <div class="mt-5 text-center">
            <h3 class="h4 fw-bold mb-4 text-dark">Try Other Industry Demos</h3>
            <div class="row g-3 justify-content-center">
                @php
                $industries = [
                    'healthcare' => ['name' => 'Healthcare', 'icon' => 'fa-heart-pulse', 'color' => 'text-danger'],
                    'education' => ['name' => 'Education', 'icon' => 'fa-graduation-cap', 'color' => 'text-primary'],
                    'automotive' => ['name' => 'Automotive', 'icon' => 'fa-car', 'color' => 'text-success'],
                    'ecommerce' => ['name' => 'E-commerce', 'icon' => 'fa-cart-shopping', 'color' => 'text-info'],
                    'hospitality' => ['name' => 'Hotels', 'icon' => 'fa-bed', 'color' => 'text-secondary'],
                    'realestate' => ['name' => 'Real Estate', 'icon' => 'fa-house', 'color' => 'text-warning']
                ];
                @endphp

                @foreach($industries as $key => $info)
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="{{ route('demo', ['industry' => $key]) }}" class="text-decoration-none">
                            <div class="border rounded-3 p-3 h-100 {{ $industry === $key ? 'border-primary bg-light' : 'border-secondary-subtle' }}">
                                <div class="text-center">
                                    <i class="fa-solid {{ $info['icon'] }} fs-3 mb-2 {{ $info['color'] }}"></i>
                                    <div class="fw-semibold small text-dark">{{ $info['name'] }}</div>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- CTA Section -->
        <section class="mt-5 text-center text-white rounded-3 p-5" style="background: linear-gradient(90deg, #2563eb, #7c3aed);">
            <h3 class="display-6 fw-bold mb-3">Ready to Transform Your Business?</h3>
            <p class="lead mb-4">See how AI Chat Support can work for your industry</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="{{ route('contact') }}" class="btn btn-light btn-lg fw-semibold">Get Started Today</a>
                <a href="https://ai-chat.support" class="btn btn-outline-light btn-lg fw-semibold">Learn More</a>
            </div>
        </section>
    </div>

    <style>
    .typing-indicator span {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #9CA3AF;
        animation: typing-bounce 1.5s infinite ease-in-out;
    }
    .typing-indicator span:nth-child(2) { animation-delay: 0.15s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.3s; }
    @keyframes typing-bounce {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
        30% { transform: translateY(-8px); opacity: 1; }
    }
    #chatMessages { scroll-behavior: smooth; }
    </style>

    <script>
    document.addEventListener('livewire:load', function () {
        const chatContainer = document.getElementById('chatMessages');
        if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
    });
    document.addEventListener('livewire:update', function () {
        setTimeout(() => {
            const chatContainer = document.getElementById('chatMessages');
            if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
        }, 50);
    });
    document.addEventListener('livewire:component:update', function () {
        setTimeout(() => {
            const chatContainer = document.getElementById('chatMessages');
            if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
        }, 10);
    });
    </script>
</div>
