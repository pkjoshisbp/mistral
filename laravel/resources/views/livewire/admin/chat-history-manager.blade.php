<div>
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Chat History</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Chat History</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-filter mr-2"></i> Filters</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-md-3 mb-2">
                            <input type="text" class="form-control" placeholder="Search" wire:model.live="search">
                        </div>
                        <div class="col-12 col-md-3 mb-2">
                            <select class="form-control" wire:model.live="organizationId">
                                <option value="">All Organizations</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-2 mb-2"><input type="date" class="form-control" wire:model.live="dateFrom" placeholder="From date"></div>
                        <div class="col-6 col-md-2 mb-2"><input type="date" class="form-control" wire:model.live="dateTo" placeholder="To date"></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-comments mr-2"></i> Conversations ({{ $conversations->total() }})</h3></div>
                <div class="card-body p-2">
                    @forelse($conversations as $conversation)
                        @php
                            $latestWidgetFeedback = collect(data_get($conversation->metadata, 'widget_feedback', []))
                                ->filter(fn ($entry) => is_array($entry))
                                ->last();
                        @endphp
                        <div class="card border mb-2 shadow-sm conversation-card">
                            {{-- Card header: date + org + action buttons --}}
                            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-1 py-2 px-3">
                                <div class="me-2">
                                    <time class="local-ts font-weight-bold" data-utc="{{ $conversation->created_at->utc()->toISOString() }}">{{ $conversation->created_at->format('Y-m-d H:i') }}</time>
                                    <span class="badge badge-secondary ml-1">{{ $conversation->organization->name ?? 'N/A' }}</span>
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <button class="btn btn-sm btn-outline-primary" wire:click="openConversationModal({{ $conversation->id }})" title="View conversation">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <a class="btn btn-sm btn-outline-success" href="{{ route('admin.chat-history.export', ['id' => $conversation->id]) }}" title="Export conversation">
                                        <i class="fas fa-file-export"></i> Export
                                    </a>
                                    <button class="btn btn-sm btn-outline-warning" wire:click="openDebugModal({{ $conversation->id }})" title="AI Debug Info">
                                        <i class="fas fa-bug"></i> Debug
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" wire:click="deleteConversation({{ $conversation->id }})" wire:confirm="Delete this conversation and refund its linked token usage?" title="Delete conversation and refund tokens">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                            {{-- Card body: visitor info + message count --}}
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div>
                                        <strong>{{ $conversation->visitor_name ?? 'Anonymous' }}</strong>
                                        @if($conversation->visitor_email)
                                            <small class="text-muted d-block">{{ $conversation->visitor_email }}</small>
                                        @endif
                                        @if($conversation->visitor_country || $conversation->visitor_location)
                                            <small class="text-muted d-block">
                                                <i class="fas fa-map-marker-alt"></i>
                                                {{ $conversation->visitor_location }}{{ $conversation->visitor_location && $conversation->visitor_country ? ', ' : '' }}{{ $conversation->visitor_country }}
                                            </small>
                                        @endif
                                        @if($latestWidgetFeedback)
                                            <div class="mt-2">
                                                <span class="badge {{ !empty($latestWidgetFeedback['helpful']) ? 'badge-success' : 'badge-warning text-dark' }}">
                                                    {{ !empty($latestWidgetFeedback['helpful']) ? 'Helpful' : 'Need more help' }}
                                                </span>
                                                @if(!empty($latestWidgetFeedback['feedback']))
                                                    <small class="d-block text-muted mt-1">{{ \Illuminate\Support\Str::limit($latestWidgetFeedback['feedback'], 120) }}</small>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <span class="badge badge-info ml-2 align-self-start">{{ $conversation->messages->count() }} msg{{ $conversation->messages->count() !== 1 ? 's' : '' }}</span>
                                </div>
                            </div>
                            {{-- Inline expanded messages (when triggered) --}}
                            @if(isset($showDetails[$conversation->id]))
                                <div class="card-footer bg-light p-3" id="conv-{{ $conversation->id }}">
                                    <div class="chat-messages mb-3" style="max-height: 320px; overflow-y: auto;">
                                        @foreach($conversation->messages as $message)
                                            @php 
                                                $isUser = ($message->sender_type === 'user');
                                                $sender = method_exists($message, 'getSenderDisplayName') ? $message->getSenderDisplayName() : ($message->sender_name ?? ucfirst($message->sender_type ?? 'System'));
                                                $msgTs = ($message->sent_at ?? $message->created_at)->utc()->toISOString();
                                                $timeFallback = ($message->sent_at ?? $message->created_at)->format('h:i A');
                                            @endphp
                                            <div class="mb-2">
                                                <div class="small mb-1">
                                                    <span class="badge {{ $isUser ? 'bg-primary' : 'bg-secondary' }}">{{ $sender }}</span>
                                                    <time class="text-muted ms-2 local-ts" data-utc="{{ $msgTs }}">{{ $timeFallback }}</time>
                                                </div>
                                                <div class="message-content {{ $isUser ? 'bg-primary text-white' : 'bg-white border' }} d-inline-block p-2 rounded" style="max-width:100%">
                                                    {!! $message->message_html !!}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <label class="form-label">Agent Reply</label>
                                    <textarea class="form-control" rows="3" wire:model.defer="replyMessage.{{ $conversation->id }}" placeholder="Type your reply as a support agent..."></textarea>
                                    <div class="d-flex justify-content-end mt-2">
                                        <button class="btn btn-sm btn-outline-primary" wire:click="sendAgentReply({{ $conversation->id }})">
                                            <i class="fas fa-reply"></i> Send as Agent
                                        </button>
                                    </div>
                                    <small class="text-muted">Agent replies are visible to customers and used as AI context for follow-ups.</small>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center p-4 text-muted">No conversations found.</div>
                    @endforelse
                </div>
                <div class="card-footer">{{ $conversations->links() }}</div>
            </div>
        </div>
    </section>
    <style>
    .chat-messages > div {
        margin-bottom: 0.5rem !important;
    }
    .chat-messages .mb-3 { margin-bottom: 0.4rem !important; }
    .chat-messages .mb-1 { margin-bottom: 0.15rem !important; }
    .chat-messages .small {
        margin-bottom: 2px !important;
    }
.chat-messages .message-content {
    word-break: break-word;
    white-space: normal;
        line-height: 1.25;
    padding: 4px 8px !important;
}
.chat-messages .message-content p,
.chat-messages .message-content ul,
.chat-messages .message-content ol {
    margin: 0 0 4px 0 !important;
}
.chat-messages .message-content p:last-child,
.chat-messages .message-content ul:last-child,
.chat-messages .message-content ol:last-child {
    margin-bottom: 0 !important;
}
.chat-messages .message-content li {
    margin-bottom: 2px !important;
}
.chat-messages .message-content a {
    color: #0d6efd;
    text-decoration: underline;
}
.chat-messages .text-white .message-content a,
.chat-messages .message-content.text-white a {
    color: #e5f0ff;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function convertLocalTs() {
        document.querySelectorAll('time.local-ts[data-utc]').forEach(function (el) {
            var d = new Date(el.dataset.utc);
            if (!isNaN(d)) {
                el.textContent = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
            }
        });
    }
    convertLocalTs();
    // Re-run after Livewire re-renders (details expand)
    document.addEventListener('livewire:updated', function() {
        convertLocalTs();
        // Also re-run inside freshly opened modal
        var modal = document.getElementById('chatConvModal');
        if (modal) modal.querySelectorAll('time.local-ts[data-utc]').forEach(function(el) {
            var d = new Date(el.dataset.utc);
            if (!isNaN(d)) el.textContent = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',second:'2-digit'});
        });
    });

    // Show/hide conversation modal — jQuery (Bootstrap 4 / AdminLTE)
    window.addEventListener('show-chat-modal', function () {
        if (typeof $ !== 'undefined') {
            $('#chatConvModal').modal('show');
        }
    });
    window.addEventListener('hide-chat-modal', function () {
        if (typeof $ !== 'undefined') {
            $('#chatConvModal').modal('hide');
        }
    });
    // When modal is dismissed via backdrop/ESC, sync Livewire state
    $(document).on('hidden.bs.modal', '#chatConvModal', function () {
        var component = window.Livewire && document.querySelector('[wire\\:id]');
        if (component) {
            var id = component.getAttribute('wire:id');
            if (id) Livewire.find(id).call('closeConversationModal');
        }
    });

    // Debug modal
    window.addEventListener('show-debug-modal', function () {
        if (typeof $ !== 'undefined') {
            $('#debugModal').modal('show');
            // Convert timestamps inside the modal after Livewire populates it
            setTimeout(function () { convertLocalTs(); }, 100);
        }
    });
    window.addEventListener('hide-debug-modal', function () {
        if (typeof $ !== 'undefined') {
            $('#debugModal').modal('hide');
        }
    });
    $(document).on('hidden.bs.modal', '#debugModal', function () {
        var component = window.Livewire && document.querySelector('[wire\\:id]');
        if (component) {
            var id = component.getAttribute('wire:id');
            if (id) Livewire.find(id).call('closeDebugModal');
        }
    });
});
</script>

{{-- LLM Debug Modal --}}
<div class="modal fade" id="debugModal" tabindex="-1" role="dialog" aria-labelledby="debugModalLabel" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="debugModalLabel">
                    <i class="fas fa-bug mr-2"></i> AI Debug Info
                    @if($debugConversationId)
                        <small class="ml-2 text-muted">#{{ $debugConversationId }}</small>
                    @endif
                </h5>
                <button type="button" class="close" wire:click="closeDebugModal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0" style="max-height:72vh;overflow-y:auto;">
                @if(empty($debugLogs))
                    <div class="text-center p-5 text-muted">
                        <i class="fas fa-info-circle fa-2x mb-2 d-block"></i>
                        No debug records found for this conversation.<br>
                        <small>Debug logs are written from the next chat message onward.</small>
                    </div>
                @else
                    @foreach($debugLogs as $idx => $log)
                    @php
                        $extra = is_array($log['extra'] ?? null) ? $log['extra'] : [];
                        $faqContactDrift = is_array(data_get($extra, 'faq_contact_drift')) ? data_get($extra, 'faq_contact_drift') : [];
                        $topMatches = is_array(data_get($extra, 'top_matches')) ? data_get($extra, 'top_matches') : [];
                        $pendingFollowUp = is_array(data_get($extra, 'pending_follow_up')) ? data_get($extra, 'pending_follow_up') : [];
                        $retrievalTiming = is_array(data_get($extra, 'retrieval_timing')) ? data_get($extra, 'retrieval_timing') : [];
                        $debugExport = [
                            'user_message' => $log['user_message'] ?? null,
                            'intent' => $log['intent'] ?? null,
                            'intent_confidence' => $log['intent_confidence'] ?? null,
                            'intent_method' => $log['intent_method'] ?? null,
                            'response_path' => $log['response_path'] ?? null,
                            'best_qdrant_score' => $log['best_qdrant_score'] ?? null,
                            'original_query' => $log['original_query'] ?? null,
                            'final_search_query' => $log['final_search_query'] ?? null,
                            'query_was_rewritten' => $log['query_was_rewritten'] ?? null,
                            'rewritten_query' => $log['rewritten_query'] ?? null,
                            'faq_source' => data_get($extra, 'faq_source'),
                            'faq_item_id' => data_get($extra, 'faq_item_id'),
                            'faq_title' => data_get($extra, 'faq_title'),
                            'top_matches' => $topMatches,
                            'previous_query' => data_get($extra, 'previous_query'),
                            'previous_response_path' => data_get($extra, 'previous_response_path'),
                            'previous_faq_id' => data_get($extra, 'previous_faq_id'),
                            'previous_faq_title' => data_get($extra, 'previous_faq_title'),
                            'context_reused' => data_get($extra, 'context_reused'),
                            'reason' => data_get($extra, 'reason'),
                            'pending_follow_up' => $pendingFollowUp,
                            'branch_type' => data_get($extra, 'branch_type'),
                            'branch_score' => data_get($extra, 'branch_score'),
                            'timing' => [
                                'search_elapsed_ms' => $log['search_elapsed_ms'] ?? null,
                                'retrieval_timing' => $retrievalTiming,
                                'llm_elapsed_ms' => $log['llm_elapsed_ms'] ?? null,
                                'total_elapsed_ms' => $log['total_elapsed_ms'] ?? null,
                            ],
                        ];
                    @endphp
                    <div class="border-bottom p-3 {{ $idx % 2 === 0 ? 'bg-white' : 'bg-light' }}">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <span class="badge badge-{{ match($log['response_path'] ?? '') {
                                    'faq_direct'  => 'success',
                                    'faq_branch' => 'info',
                                    'faq_keyword' => 'info',
                                    'affirmative_clarification' => 'warning',
                                    'clarification' => 'warning',
                                    default => 'secondary'
                                } }} mr-1">{{ strtoupper($log['response_path'] ?? 'llm') }}</span>
                                @if($log['clarification_sought'] ?? false)
                                    <span class="badge badge-warning mr-1"><i class="fas fa-question-circle"></i> Clarification</span>
                                @endif
                                @if($log['faq_matched'] ?? false)
                                    <span class="badge badge-success mr-1"><i class="fas fa-check"></i> FAQ Match</span>
                                @endif
                                @if($log['context_cleared'] ?? false)
                                    <span class="badge badge-danger mr-1"><i class="fas fa-times-circle"></i> Context Cleared</span>
                                @endif
                                @if($log['low_relevance_warning'] ?? false)
                                    <span class="badge badge-warning mr-1"><i class="fas fa-exclamation-triangle"></i> Low Relevance</span>
                                @endif
                            </div>
                            <small class="text-muted"><time class="local-ts" data-utc="{{ \Carbon\Carbon::parse($log['created_at'])->utc()->toISOString() }}">{{ $log['created_at'] }}</time></small>
                        </div>

                        {{-- User message --}}
                        @if($log['user_message'] ?? null)
                            <div class="mb-2">
                                <strong><i class="fas fa-comment mr-1 text-primary"></i>User:</strong>
                                <span class="text-dark">{{ $log['user_message'] }}</span>
                            </div>
                        @endif

                        <div class="row">
                            {{-- Intent --}}
                            <div class="col-12 col-md-4 mb-2">
                                <div class="card card-body p-2 h-100 bg-light border">
                                    <div class="small font-weight-bold text-muted mb-1"><i class="fas fa-brain mr-1"></i>Intent Detection</div>
                                    <div><strong>Intent:</strong> <code>{{ $log['intent'] ?? '—' }}</code></div>
                                    <div><strong>Confidence:</strong>
                                        @php $ic = $log['intent_confidence'] ?? null; @endphp
                                        @if($ic !== null)
                                            <span class="badge badge-{{ $ic >= 0.7 ? 'success' : ($ic >= 0.5 ? 'warning' : 'danger') }}">{{ number_format($ic * 100, 1) }}%</span>
                                        @else —
                                        @endif
                                    </div>
                                    <div><strong>Method:</strong> <code>{{ $log['intent_method'] ?? '—' }}</code></div>
                                </div>
                            </div>

                            {{-- Search / Retrieval --}}
                            <div class="col-12 col-md-4 mb-2">
                                <div class="card card-body p-2 h-100 bg-light border">
                                    <div class="small font-weight-bold text-muted mb-1"><i class="fas fa-search mr-1"></i>Search / Retrieval</div>
                                    <div><strong>Qdrant Score:</strong>
                                        @php $score = $log['best_qdrant_score'] ?? null; @endphp
                                        @if($score !== null)
                                            <span class="badge badge-{{ $score >= 0.7 ? 'success' : ($score >= 0.6 ? 'warning' : 'danger') }}">{{ number_format($score, 4) }}</span>
                                            @if($score < 0.60) <small class="text-danger">(low → clarification)</small> @endif
                                        @else —
                                        @endif
                                    </div>
                                    <div><strong>FAQ source:</strong> <code>{{ data_get($extra, 'faq_source', '—') }}</code></div>
                                    @if(data_get($extra, 'faq_title'))
                                        <div><strong>FAQ title:</strong> <span class="text-muted">{{ Str::limit(data_get($extra, 'faq_title'), 80) }}</span></div>
                                    @endif
                                    @if(data_get($extra, 'faq_item_id'))
                                        <div><strong>FAQ item:</strong> <code>{{ data_get($extra, 'faq_item_id') }}</code></div>
                                    @endif
                                    @if(data_get($extra, 'faq_semantic_threshold') !== null)
                                        <div><strong>FAQ semantic:</strong>
                                            <span class="text-muted">
                                                best {{ data_get($extra, 'faq_semantic_best_score') !== null ? number_format((float) data_get($extra, 'faq_semantic_best_score'), 4) : '—' }}
                                                / threshold {{ number_format((float) data_get($extra, 'faq_semantic_threshold'), 2) }}
                                            </span>
                                        </div>
                                    @endif
                                    @if($log['faq_keyword_score'] ?? null)
                                        <div><strong>FAQ keyword score:</strong> <span class="badge badge-warning">{{ number_format((float) $log['faq_keyword_score'], 2) }}</span></div>
                                    @endif
                                    @if(data_get($extra, 'faq_specific_coverage') !== null)
                                        <div><strong>Keyword coverage:</strong> {{ number_format((float) data_get($extra, 'faq_specific_coverage') * 100, 1) }}%</div>
                                    @endif
                                    @if(!empty(data_get($extra, 'faq_specific_overlap_terms', [])))
                                        <div><strong>Specific overlap:</strong> <span class="text-muted">{{ implode(', ', data_get($extra, 'faq_specific_overlap_terms', [])) }}</span></div>
                                    @endif
                                    <div><strong>Rewritten?</strong> {{ ($log['query_was_rewritten'] ?? false) ? '✅ Yes' : '❌ No' }}</div>
                                    @if(($log['rewritten_query'] ?? null) && $log['rewritten_query'] !== $log['original_query'])
                                        <div><strong>Rewrite:</strong> <em class="text-muted">{{ Str::limit($log['rewritten_query'] ?? '', 80) }}</em></div>
                                    @endif
                                    <div><strong>Context len:</strong> {{ $log['context_length'] ?? '—' }} chars</div>
                                    @if($log['search_elapsed_ms'] ?? null)
                                        <div><strong>Search time:</strong> {{ $log['search_elapsed_ms'] }}ms</div>
                                    @endif
                                    @if(!empty($retrievalTiming))
                                        <div><strong>Retrieval split:</strong>
                                            <span class="text-muted">
                                                @foreach($retrievalTiming as $label => $ms)
                                                    {{ str_replace('_', ' ', $label) }} {{ $ms }}ms{{ !$loop->last ? ',' : '' }}
                                                @endforeach
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- LLM --}}
                            <div class="col-12 col-md-4 mb-2">
                                <div class="card card-body p-2 h-100 bg-light border">
                                    <div class="small font-weight-bold text-muted mb-1"><i class="fas fa-robot mr-1"></i>Model / Timing</div>
                                    <div><strong>Model:</strong> <code>{{ $log['model_used'] ?? '—' }}</code></div>
                                    <div><strong>Provider:</strong> <code>{{ $log['ai_provider'] ?? '—' }}</code></div>
                                    @if(data_get($extra, 'requested_model') && data_get($extra, 'requested_model') !== ($log['model_used'] ?? null))
                                        <div><strong>Requested model:</strong> <code>{{ data_get($extra, 'requested_model') }}</code></div>
                                    @endif
                                    @if(data_get($extra, 'fallback_used'))
                                        <div><strong>Fallback used:</strong> <span class="badge badge-warning">Yes</span></div>
                                    @endif
                                    @if(data_get($extra, 'backend_used'))
                                        <div><strong>Backend path:</strong> <code>{{ data_get($extra, 'backend_used') }}</code></div>
                                    @endif
                                    @if(data_get($extra, 'requested_url') && data_get($extra, 'actual_url') && data_get($extra, 'requested_url') !== data_get($extra, 'actual_url'))
                                        <div><strong>Endpoint:</strong> <span class="text-muted">{{ data_get($extra, 'actual_url') }}</span></div>
                                    @endif
                                    <div><strong>Max tokens:</strong> {{ $log['max_tokens'] ?? '—' }}</div>
                                    <div><strong>FAQ paraphrase:</strong> {{ data_get($extra, 'faq_paraphrase_attempted') ? 'Yes' : 'No' }}</div>
                                    @if(data_get($extra, 'faq_paraphrase_model'))
                                        <div><strong>Paraphrase model:</strong> <code>{{ data_get($extra, 'faq_paraphrase_model') }}</code></div>
                                    @endif
                                    @if(data_get($extra, 'faq_paraphrase_elapsed_ms'))
                                        <div><strong>Paraphrase time:</strong> {{ data_get($extra, 'faq_paraphrase_elapsed_ms') }}ms</div>
                                    @endif
                                    <div><strong>Contacts sanitized:</strong> {{ data_get($extra, 'faq_contacts_sanitized') ? '✅' : '❌' }}</div>
                                    @if(!empty($faqContactDrift['added_emails'] ?? []))
                                        <div><strong>Added emails:</strong> <span class="text-danger">{{ implode(', ', $faqContactDrift['added_emails']) }}</span></div>
                                    @endif
                                    @if(!empty($faqContactDrift['added_domains'] ?? []))
                                        <div><strong>Added domains:</strong> <span class="text-danger">{{ implode(', ', $faqContactDrift['added_domains']) }}</span></div>
                                    @endif
                                    @if($log['input_tokens'] ?? null)
                                        <div><strong>Tokens in/out:</strong> {{ $log['input_tokens'] }} / {{ $log['output_tokens'] ?? '?' }}</div>
                                    @endif
                                    <div><strong>LLM time:</strong> {{ $log['llm_elapsed_ms'] ? $log['llm_elapsed_ms'].'ms' : '—' }}</div>
                                    <div><strong>Total time:</strong> {{ $log['total_elapsed_ms'] ? $log['total_elapsed_ms'].'ms' : '—' }}</div>
                                    <div><strong>Envelope OK:</strong> {{ ($log['envelope_parse_ok'] ?? false) ? '✅' : '❌' }}</div>
                                    @if(is_array(data_get($extra, 'connection_failure')))
                                        <div><strong>Connection failure:</strong> <span class="text-danger">{{ data_get($extra, 'connection_failure.error', 'Unknown error') }}</span></div>
                                    @elseif(is_array(data_get($extra, 'fallback_reason')))
                                        <div><strong>Fallback reason:</strong> <span class="text-danger">{{ json_encode(data_get($extra, 'fallback_reason')) }}</span></div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if(!empty(data_get($extra, 'attempts', [])))
                            <div class="mt-2">
                                <div class="small font-weight-bold text-muted mb-1"><i class="fas fa-network-wired mr-1"></i>Backend Attempts</div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0 bg-white">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Attempt</th>
                                                <th>Model</th>
                                                <th>Backend</th>
                                                <th>Status</th>
                                                <th>Time</th>
                                                <th>Error</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach((array) data_get($extra, 'attempts', []) as $attempt)
                                                <tr>
                                                    <td>{{ $attempt['attempt'] ?? ($attempt['url'] ?? '—') }}</td>
                                                    <td>{{ $attempt['model'] ?? '—' }}</td>
                                                    <td>{{ $attempt['backend'] ?? ($attempt['url'] ?? '—') }}</td>
                                                    <td>{!! !empty($attempt['successful']) ? '<span class="text-success">OK</span>' : '<span class="text-danger">Failed</span>' !!}</td>
                                                    <td>{{ isset($attempt['attempt_ms']) ? ((int) $attempt['attempt_ms']).'ms' : '—' }}</td>
                                                    <td><span class="text-danger">{{ $attempt['error'] ?? '—' }}</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Query expansion --}}
                        @if($log['expansion_attempted'] ?? false)
                            <div class="alert alert-info py-1 px-2 mt-1 small mb-1">
                                <i class="fas fa-expand-arrows-alt mr-1"></i>
                                <strong>Query Expansion attempted.</strong>
                                @if($log['expanded_query'] ?? null)
                                    Expanded to: <em>"{{ $log['expanded_query'] }}"</em>
                                    @if($log['expansion_score_gain'] ?? null)
                                        — score gain: <strong>+{{ number_format($log['expansion_score_gain'], 4) }}</strong>
                                    @endif
                                @else
                                    (expansion returned no improvement)
                                @endif
                            </div>
                        @endif

                        {{-- Queries detail row --}}
                        <div class="row mt-1">
                            <div class="col-12 col-md-6 small">
                                <strong><i class="fas fa-pencil-alt mr-1"></i>Original query:</strong>
                                <span class="text-muted">{{ $log['original_query'] ?? '—' }}</span>
                            </div>
                            @if(($log['final_search_query'] ?? '') !== ($log['original_query'] ?? ''))
                            <div class="col-12 col-md-6 small">
                                <strong><i class="fas fa-arrow-right mr-1"></i>Final search query:</strong>
                                <span class="text-info">{{ $log['final_search_query'] ?? '—' }}</span>
                            </div>
                            @endif
                        </div>

                        @if(!empty($topMatches))
                            <div class="mt-3">
                                <div class="small font-weight-bold text-muted mb-1"><i class="fas fa-list-ol mr-1"></i>Top Matches Considered</div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0 bg-white">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>FAQ</th>
                                                <th>Score</th>
                                                <th>Title</th>
                                                <th>Category</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($topMatches as $match)
                                                <tr>
                                                    <td><code>{{ data_get($match, 'faq_id', '—') }}</code></td>
                                                    <td>{{ data_get($match, 'score', '—') }}</td>
                                                    <td>{{ data_get($match, 'title', '—') }}</td>
                                                    <td>{{ data_get($match, 'category', data_get($match, 'data_type', '—')) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if(data_get($extra, 'previous_query') || array_filter($pendingFollowUp) || array_key_exists('context_reused', $extra) || data_get($extra, 'reason'))
                            <div class="mt-3">
                                <div class="small font-weight-bold text-muted mb-1"><i class="fas fa-history mr-1"></i>Previous Context</div>
                                <div class="card card-body p-2 bg-white border small">
                                    @if(data_get($extra, 'previous_query'))
                                        <div><strong>Previous query:</strong> <span class="text-muted">{{ data_get($extra, 'previous_query') }}</span></div>
                                    @endif
                                    @if(data_get($extra, 'previous_faq_id') || data_get($extra, 'previous_faq_title'))
                                        <div>
                                            <strong>Previous FAQ:</strong>
                                            <span class="text-muted">{{ data_get($extra, 'previous_faq_id', '—') }}{{ data_get($extra, 'previous_faq_title') ? ' - ' . data_get($extra, 'previous_faq_title') : '' }}</span>
                                        </div>
                                    @endif
                                    @if(array_key_exists('context_reused', $extra))
                                        <div><strong>Context reused:</strong> {{ data_get($extra, 'context_reused') ? 'true' : 'false' }}</div>
                                    @endif
                                    @if(data_get($extra, 'reason'))
                                        <div><strong>Reason:</strong> <code>{{ data_get($extra, 'reason') }}</code></div>
                                    @endif
                                    @if(data_get($pendingFollowUp, 'question'))
                                        <div><strong>Pending question:</strong> <span class="text-muted">{{ data_get($pendingFollowUp, 'question') }}</span></div>
                                    @endif
                                    @if(data_get($pendingFollowUp, 'resolved_anchor'))
                                        <div><strong>Pending anchor:</strong> <span class="text-muted">{{ data_get($pendingFollowUp, 'resolved_anchor') }}</span></div>
                                    @endif
                                    @if(!empty(data_get($pendingFollowUp, 'topics', [])))
                                        <div><strong>Pending topics:</strong> <span class="text-muted">{{ implode(', ', data_get($pendingFollowUp, 'topics', [])) }}</span></div>
                                    @endif
                                    @if(data_get($extra, 'branch_type') || data_get($extra, 'branch_score') !== null)
                                        <div><strong>Branch decision:</strong> <span class="text-muted">{{ data_get($extra, 'branch_type', '—') }} @if(data_get($extra, 'branch_score') !== null)(score {{ number_format((float) data_get($extra, 'branch_score'), 2) }})@endif</span></div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="mt-3">
                            <div class="small font-weight-bold text-muted mb-1"><i class="fas fa-code mr-1"></i>Pasteable Debug JSON</div>
                            <pre class="small bg-dark text-light rounded p-3 mb-0" style="white-space:pre-wrap;word-break:break-word;">{{ json_encode($debugExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
            <div class="modal-footer">
                <small class="text-muted mr-auto">Showing {{ count($debugLogs) }} debug record(s). Auto-cleaned after 15 days.</small>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="closeDebugModal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Conversation Detail Modal --}}
<div class="modal fade" id="chatConvModal" tabindex="-1" role="dialog" aria-labelledby="chatConvModalLabel" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            @if($modalConversation)
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white" id="chatConvModalLabel">
                    <i class="fas fa-comments mr-2"></i>
                    {{ $modalConversation->visitor_name ?? 'Anonymous' }}
                    <small class="ml-2 opacity-75">— {{ $modalConversation->organization->name ?? '' }}</small>
                </h5>
                <button type="button" class="close text-white" wire:click="closeConversationModal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height:62vh;overflow-y:auto;">
                @if($modalConversation->visitor_email || $modalConversation->visitor_country)
                    <div class="mb-3 p-2 bg-light rounded d-flex flex-wrap gap-2" style="font-size:13px">
                        @if($modalConversation->visitor_email)
                            <span><i class="fas fa-envelope mr-1 text-muted"></i>{{ $modalConversation->visitor_email }}</span>
                        @endif
                        @if($modalConversation->visitor_location || $modalConversation->visitor_country)
                            <span><i class="fas fa-map-marker-alt mr-1 text-muted"></i>{{ $modalConversation->visitor_location }}{{ $modalConversation->visitor_location && $modalConversation->visitor_country ? ', ' : '' }}{{ $modalConversation->visitor_country }}</span>
                        @endif
                        <span class="ml-auto text-muted"><i class="far fa-clock mr-1"></i><time class="local-ts" data-utc="{{ $modalConversation->created_at->utc()->toISOString() }}">{{ $modalConversation->created_at->format('d M Y H:i:s') }}</time></span>
                    </div>
                @endif
                @php
                    $widgetFeedbackEntries = collect(data_get($modalConversation->metadata, 'widget_feedback', []))
                        ->filter(fn ($entry) => is_array($entry))
                        ->sortByDesc(fn ($entry) => $entry['submitted_at'] ?? null)
                        ->values();
                @endphp
                @if($widgetFeedbackEntries->isNotEmpty())
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white border-bottom">
                            <strong><i class="fas fa-thumbs-up mr-1 text-info"></i> Widget Feedback</strong>
                            <small class="text-muted ml-1">{{ $widgetFeedbackEntries->count() }} item{{ $widgetFeedbackEntries->count() !== 1 ? 's' : '' }}</small>
                        </div>
                        <div class="card-body py-2">
                            @foreach($widgetFeedbackEntries as $feedbackEntry)
                                <div class="widget-feedback-entry py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                    <div class="d-flex flex-wrap align-items-center mb-1">
                                        <span class="badge {{ !empty($feedbackEntry['helpful']) ? 'badge-success' : 'badge-warning text-dark' }} mr-2">
                                            {{ !empty($feedbackEntry['helpful']) ? 'Helpful' : 'Need more help' }}
                                        </span>
                                        @if(!empty($feedbackEntry['submitted_at']))
                                            <small class="text-muted">
                                                <time class="local-ts" data-utc="{{ \Carbon\Carbon::parse($feedbackEntry['submitted_at'])->utc()->toISOString() }}">{{ \Carbon\Carbon::parse($feedbackEntry['submitted_at'])->format('d M Y H:i:s') }}</time>
                                            </small>
                                        @endif
                                    </div>
                                    @if(!empty($feedbackEntry['feedback']))
                                        <div class="small text-dark mb-1">{{ $feedbackEntry['feedback'] }}</div>
                                    @endif
                                    @if(!empty($feedbackEntry['message']))
                                        <div class="small text-muted"><strong>About reply:</strong> {{ $feedbackEntry['message'] }}</div>
                                    @endif
                                    @if(!empty($feedbackEntry['page_url']))
                                        <div class="small mt-1">
                                            <a href="{{ $feedbackEntry['page_url'] }}" target="_blank" rel="noopener noreferrer">{{ $feedbackEntry['page_url'] }}</a>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="chat-messages">
                    @foreach($modalConversation->messages->sortBy('sent_at') as $message)
                        @php
                            $isUser = ($message->sender_type === 'user');
                            $sender = $message->sender_name ?? ucfirst($message->sender_type ?? 'System');
                        @endphp
                        <div class="mb-3 {{ $isUser ? 'text-right' : '' }}">
                            <div class="small mb-1">
                                @if(!$isUser)
                                    <span class="badge badge-secondary mr-1">{{ $sender }}</span>
                                @endif
                                <time class="text-muted local-ts" data-utc="{{ ($message->sent_at ?? $message->created_at)->utc()->toISOString() }}">{{ ($message->sent_at ?? $message->created_at)->format('d M H:i:s') }}</time>
                                @if($isUser)
                                    <span class="badge badge-primary ml-1">{{ $sender }}</span>
                                @endif
                            </div>
                            <div class="{{ $isUser ? 'bg-primary text-white' : 'bg-light border' }} d-inline-block p-2 rounded" style="max-width:85%;word-break:break-word;text-align:left">
                                {!! $message->message_html !!}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer flex-column align-items-stretch bg-light">
                <label class="font-weight-bold text-left mb-1"><i class="fas fa-reply mr-1"></i> Agent Reply</label>
                <textarea class="form-control mb-2" rows="3" wire:model.defer="replyMessage.{{ $modalConversation->id }}" placeholder="Type your reply as a support agent..."></textarea>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Agent replies are visible to customers and used as AI context.</small>
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm mr-2" wire:click="deleteConversation({{ $modalConversation->id }})" wire:confirm="Delete this conversation and refund its linked token usage?">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm mr-2" wire:click="closeConversationModal">Close</button>
                        <button class="btn btn-primary btn-sm" wire:click="sendAgentReply({{ $modalConversation->id }})">
                            <i class="fas fa-reply"></i> Send as Agent
                        </button>
                    </div>
                </div>
            </div>
            @else
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-info" role="status"><span class="sr-only">Loading...</span></div>
            </div>
            @endif
        </div>
    </div>
</div>
</div>

