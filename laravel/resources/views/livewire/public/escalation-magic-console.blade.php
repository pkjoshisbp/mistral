<div class="container py-4" wire:poll.5s="refreshConversation">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">Escalation Console</h4>
                </div>
                <div class="card-body">
                    @if (session()->has('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if (!empty($errorMessage))
                        <div class="alert alert-danger" role="alert">
                            {{ $errorMessage }}
                        </div>
                    @endif

                    @if ($isValid && $conversation)
                        <div class="mb-3">
                            <div class="d-flex flex-wrap gap-3">
                                <div>
                                    <strong>Organization:</strong> {{ $conversation->organization?->name ?? 'N/A' }}
                                </div>
                                <div>
                                    <strong>Visitor:</strong> {{ $conversation->visitor_name ?? 'Anonymous' }}
                                </div>
                                <div>
                                    <strong>Session ID:</strong> {{ $conversation->conversation_id ?? '' }}
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-3 mb-3" style="max-height: 420px; overflow-y: auto; background: #f8f9fa;">
                            @foreach($conversation->messages as $message)
                                @php
                                    $isUser = ($message->sender_type === 'user');
                                    $sender = method_exists($message, 'getSenderDisplayName') ? $message->getSenderDisplayName() : ($message->sender_name ?? ucfirst($message->sender_type ?? 'System'));
                                    $sentAt = $message->sent_at ?? $message->created_at;
                                    $time = $sentAt ? $sentAt->timezone($orgTimezone)->format('H:i') : '';
                                @endphp
                                <div class="mb-3">
                                    <div class="small mb-1">
                                        <span class="badge {{ $isUser ? 'bg-primary' : 'bg-secondary' }}">{{ $sender }}</span>
                                        <span class="text-muted ms-2">{{ $time }}</span>
                                    </div>
                                    <div class="message-content {{ $isUser ? 'bg-primary text-white' : 'bg-white border' }} d-inline-block p-2 rounded">
                                        {!! $message->message_html !!}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div>
                            <label class="form-label">Reply</label>
                            <textarea class="form-control" rows="4" wire:model.defer="replyMessage" placeholder="Type your reply..."></textarea>
                            <div class="d-flex justify-content-end mt-2">
                                <button type="button" class="btn btn-primary" wire:click="sendAgentReply">Send Reply</button>
                            </div>
                        </div>
                    @else
                        <p class="text-muted mb-0">This escalation link is no longer valid.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
