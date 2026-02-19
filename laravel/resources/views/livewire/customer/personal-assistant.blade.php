<div>
    @if($hasAssistantAccess)
        <div class="alert alert-success">
            <strong>Plan Status:</strong> {{ $assistantPlanMessage }}
            @if($assistantPlanStatus === 'trial' && $assistantTrialEndsAt)
                <br><small>Trial ends at: {{ $assistantTrialEndsAt }}</small>
            @endif
        </div>
    @else
        <div class="alert alert-warning">
            <strong>Plan Status:</strong> {{ $assistantPlanMessage }}
        </div>
    @endif

    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-cog mr-2"></i> Voice Profile</h5>
                </div>
                <div class="card-body">
                    <div class="form-group mb-3">
                        <label>Preferred Language</label>
                        <select class="form-control" wire:model="preferredLanguage">
                            <option value="en">English</option>
                            <option value="hi">Hindi</option>
                            <option value="te">Telugu</option>
                            <option value="ta">Tamil</option>
                            <option value="bn">Bengali</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label>TTS Provider</label>
                        <select class="form-control" wire:model="ttsProvider">
                            <option value="xtts">XTTS v2 (Primary)</option>
                            <option value="indic">Indic TTS</option>
                            <option value="auto">Auto</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label>Custom Vocabulary (one per line)</label>
                        <textarea class="form-control" rows="4" wire:model="customVocabularyText" placeholder="Product names, team names, brand words"></textarea>
                    </div>

                    <div class="form-group mb-3">
                        <label>Correction Map (format: wrong => correct)</label>
                        <textarea class="form-control" rows="4" wire:model="correctionMapText" placeholder="woodin => wooden&#10;calender => calendar"></textarea>
                    </div>

                    <button type="button" class="btn btn-primary" wire:click="saveProfile">
                        <i class="fas fa-save mr-1"></i> Save Profile
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-graduation-cap mr-2"></i> Onboarding & Voice Training</h5>
                    <span class="badge {{ $onboardingCompleted ? 'bg-success' : 'bg-warning text-dark' }}">
                        {{ $onboardingCompleted ? 'Completed' : 'In Progress' }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border">
                        <strong>Progress:</strong> {{ $verifiedSampleCount }} / {{ $minimumVerifiedSamples }} verified samples.
                        <br><small>Read the selected sample, transcribe, correct, and save. You can retry unlimited times.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label>Training Mode</label>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn {{ $trainingMode === 'sentences' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setTrainingMode('sentences')">Sentences</button>
                            <button type="button" class="btn {{ $trainingMode === 'phrases' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setTrainingMode('phrases')">Words / Phrases</button>
                            <button type="button" class="btn {{ $trainingMode === 'paragraphs' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setTrainingMode('paragraphs')">Paragraphs</button>
                        </div>
                    </div>

                    @if($onboardingStatus)
                        <div class="alert alert-info py-2">
                            <strong>Status:</strong> {{ $onboardingStatus }}
                        </div>
                    @endif

                    <div class="form-group mb-3">
                        <label>Provided Text Samples</label>
                        <div style="max-height: 180px; overflow-y: auto;" class="border rounded p-2">
                            @foreach($this->currentTrainingSamples as $sample)
                                <button
                                    type="button"
                                    class="btn btn-sm w-100 text-start mb-2 {{ $selectedTrainingText === $sample ? 'btn-info' : 'btn-outline-secondary' }}"
                                    wire:click="selectTrainingText(@js($sample))"
                                >
                                    {{ $sample }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <label>Selected Text to Read</label>
                        <div class="border rounded p-2 bg-light">{{ $selectedTrainingText ?: 'Select a sample above.' }}</div>
                    </div>

                    <input id="onboardingAudioInput" type="file" class="d-none" wire:model="onboardingAudioFile" accept="audio/*">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <button type="button" class="btn btn-outline-danger" onclick="startPaRecording('onboarding')" @if(!$hasAssistantAccess) disabled @endif>
                            <i class="fas fa-microphone mr-1"></i> Start Mic
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="stopPaRecording('onboarding')" @if(!$hasAssistantAccess) disabled @endif>
                            <i class="fas fa-stop mr-1"></i> Stop
                        </button>
                        <button type="button" class="btn btn-outline-dark" onclick="document.getElementById('onboardingAudioInput').click()" @if(!$hasAssistantAccess) disabled @endif>
                            <i class="fas fa-upload mr-1"></i> Use Audio File
                        </button>
                        <button type="button" class="btn btn-outline-primary" wire:click="transcribeOnboardingSample" wire:loading.attr="disabled" @if(!$hasAssistantAccess) disabled @endif>
                            <i class="fas fa-wave-square mr-1"></i> Transcribe Sample
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span id="pa-onboarding-live-indicator" class="badge bg-danger d-none pa-mic-live">
                            <span class="pa-mic-dot"></span> Recording
                        </span>
                        <small id="pa-onboarding-recording-status" class="text-muted mb-0">Mic idle.</small>
                    </div>
                    @error('onboardingAudioFile') <small class="text-danger d-block mb-2">{{ $message }}</small> @enderror

                    <div class="form-group mb-2">
                        <label>Sample Transcript</label>
                        <textarea class="form-control" rows="2" wire:model="onboardingTranscript" readonly></textarea>
                    </div>

                    <div class="form-group mb-2">
                        <label>Edit / Correct</label>
                        <textarea class="form-control" rows="2" wire:model="onboardingEditedTranscript"></textarea>
                    </div>

                    <button type="button" class="btn btn-success" wire:click="saveOnboardingCorrection" wire:loading.attr="disabled" @if(!$hasAssistantAccess) disabled @endif>
                        <i class="fas fa-check mr-1"></i> Save Correction
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            @if(!$onboardingCompleted)
                <div class="alert alert-info">
                    <strong>Assistant Console Locked</strong><br>
                    Complete onboarding voice training ({{ $minimumVerifiedSamples }} verified samples) to unlock full command mode.
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-microphone-alt mr-2"></i> Assistant Console</h5>
                </div>
                <div class="card-body">
                    <input id="commandAudioInput" type="file" class="d-none" wire:model="audioFile" accept="audio/*">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <button type="button" class="btn btn-outline-danger" onclick="startPaRecording('command')" @if(!$hasAssistantAccess || !$onboardingCompleted) disabled @endif>
                            <i class="fas fa-microphone mr-1"></i> Start Mic
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="stopPaRecording('command')" @if(!$hasAssistantAccess || !$onboardingCompleted) disabled @endif>
                            <i class="fas fa-stop mr-1"></i> Stop
                        </button>
                        <button type="button" class="btn btn-outline-dark" onclick="document.getElementById('commandAudioInput').click()" @if(!$hasAssistantAccess || !$onboardingCompleted) disabled @endif>
                            <i class="fas fa-upload mr-1"></i> Use Audio File
                        </button>
                        <button type="button" class="btn btn-outline-primary" wire:click="transcribeVoice" wire:loading.attr="disabled" @if(!$hasAssistantAccess || !$onboardingCompleted) disabled @endif>
                            <i class="fas fa-wave-square mr-1"></i> Transcribe
                        </button>
                        <button type="button" class="btn btn-success" wire:click="processCommand" wire:loading.attr="disabled" @if(!$hasAssistantAccess || !$onboardingCompleted) disabled @endif>
                            <i class="fas fa-play mr-1"></i> Run Command
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span id="pa-command-live-indicator" class="badge bg-danger d-none pa-mic-live">
                            <span class="pa-mic-dot"></span> Recording
                        </span>
                        <small id="pa-command-recording-status" class="text-muted mb-0">Mic idle.</small>
                    </div>
                    @error('audioFile') <small class="text-danger d-block mb-2">{{ $message }}</small> @enderror

                    <div class="form-group mb-3">
                        <label>Manual Command Input</label>
                        <textarea class="form-control" rows="2" wire:model="inputText" placeholder="Type command, e.g. Add reminder for tomorrow 10 AM"></textarea>
                        @error('inputText') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group mb-3">
                        <label>Transcript</label>
                        <textarea class="form-control" rows="3" wire:model="transcript" readonly></textarea>
                    </div>

                    <div class="form-group mb-3">
                        <label>Editable Transcript</label>
                        <textarea class="form-control" rows="3" wire:model="editedTranscript"></textarea>
                    </div>

                    <div class="alert alert-info">
                        <strong>Assistant Reply:</strong><br>
                        {{ $assistantReply ?: 'No response yet.' }}
                    </div>

                    @if($actionStatus)
                        <div class="alert alert-secondary py-2">
                            <strong>Status:</strong> {{ $actionStatus }}
                        </div>
                    @endif

                    @if($audioReplyBase64)
                        <audio controls class="w-100">
                            <source src="data:{{ $audioReplyMimeType }};base64,{{ $audioReplyBase64 }}" type="{{ $audioReplyMimeType }}">
                            Your browser does not support audio playback.
                        </audio>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-history mr-2"></i> Recent Voice Interaction</h6>
                </div>
                <div class="card-body" style="max-height: 280px; overflow-y: auto;">
                    @forelse($history as $item)
                        <div class="mb-2">
                            <span class="badge {{ ($item['role'] ?? '') === 'assistant' ? 'bg-secondary' : 'bg-primary' }}">{{ ucfirst($item['role'] ?? 'user') }}</span>
                            <small class="text-muted ml-2">{{ $item['at'] ?? '' }}</small>
                            <div class="border rounded p-2 mt-1">{{ $item['text'] ?? '' }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No interactions yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-tasks mr-2"></i> Saved Items (Notes, Reminders, Tasks)</h6>
                </div>
                <div class="card-body" style="max-height: 320px; overflow-y: auto;">
                    @forelse($savedItems as $item)
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-dark text-uppercase">{{ $item['type'] }}</span>
                                <small class="text-muted">{{ $item['created_at'] ?? '' }}</small>
                            </div>
                            <div class="font-weight-bold">{{ $item['title'] ?: 'Untitled' }}</div>
                            @if(!empty($item['content']))
                                <div class="small mt-1">{{ $item['content'] }}</div>
                            @endif
                            <div class="mt-1">
                                <small class="text-muted">Status: {{ $item['status'] ?? 'pending' }}</small>
                                @if(!empty($item['due_at']))
                                    <small class="text-muted ms-2">Due: {{ $item['due_at'] }}</small>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No saved items yet. Try commands like “add note…” or “remind me tomorrow at 10”.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @once
        <style>
            .pa-mic-live {
                display: inline-flex;
                align-items: center;
                gap: .35rem;
            }

            .pa-mic-dot {
                width: .5rem;
                height: .5rem;
                border-radius: 50%;
                background: currentColor;
                box-shadow: 0 0 0 0 rgba(255, 255, 255, .5);
                animation: paMicPulse 1.1s infinite;
            }

            @keyframes paMicPulse {
                0% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.35); opacity: .65; }
                100% { transform: scale(1); opacity: 1; }
            }
        </style>
        <script>
            window.paRecorderState = {
                mediaRecorder: null,
                stream: null,
                chunks: [],
                target: null,
            };

            async function startPaRecording(target) {
                const statusEl = document.getElementById(target === 'onboarding' ? 'pa-onboarding-recording-status' : 'pa-command-recording-status');
                const liveEl = document.getElementById(target === 'onboarding' ? 'pa-onboarding-live-indicator' : 'pa-command-live-indicator');
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    if (statusEl) statusEl.textContent = 'Microphone is not supported in this browser.';
                    return;
                }

                try {
                    if (window.paRecorderState.mediaRecorder && window.paRecorderState.mediaRecorder.state === 'recording') {
                        return;
                    }

                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    const recorder = new MediaRecorder(stream);

                    window.paRecorderState.stream = stream;
                    window.paRecorderState.mediaRecorder = recorder;
                    window.paRecorderState.chunks = [];
                    window.paRecorderState.target = target;

                    recorder.ondataavailable = (event) => {
                        if (event.data && event.data.size > 0) {
                            window.paRecorderState.chunks.push(event.data);
                        }
                    };

                    recorder.onstop = () => {
                        const blob = new Blob(window.paRecorderState.chunks, { type: 'audio/webm' });
                        const file = new File([blob], `pa-${target}-${Date.now()}.webm`, { type: 'audio/webm' });
                        const inputId = target === 'onboarding' ? 'onboardingAudioInput' : 'commandAudioInput';
                        const input = document.getElementById(inputId);

                        if (input) {
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            input.files = dt.files;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        if (window.paRecorderState.stream) {
                            window.paRecorderState.stream.getTracks().forEach(track => track.stop());
                        }

                        if (statusEl) statusEl.textContent = 'Recording captured. Click Transcribe.';
                    };

                    recorder.start();
                    if (liveEl) liveEl.classList.remove('d-none');
                    if (statusEl) statusEl.textContent = 'Recording... click Stop when done.';
                } catch (error) {
                    if (liveEl) liveEl.classList.add('d-none');
                    if (statusEl) {
                        if (error && error.name === 'NotAllowedError') {
                            statusEl.textContent = 'Microphone permission was blocked. Allow microphone for this site in browser settings, then retry.';
                        } else if (error && error.name === 'NotFoundError') {
                            statusEl.textContent = 'No microphone device found. Connect a mic or use "Use Audio File".';
                        } else {
                            statusEl.textContent = 'Could not access microphone. Use "Use Audio File" or allow mic permission in browser settings.';
                        }
                    }
                }
            }

            function stopPaRecording(target) {
                const statusEl = document.getElementById(target === 'onboarding' ? 'pa-onboarding-recording-status' : 'pa-command-recording-status');
                const liveEl = document.getElementById(target === 'onboarding' ? 'pa-onboarding-live-indicator' : 'pa-command-live-indicator');
                const recorder = window.paRecorderState.mediaRecorder;
                if (!recorder || recorder.state !== 'recording' || window.paRecorderState.target !== target) {
                    if (statusEl) statusEl.textContent = 'No active recording.';
                    if (liveEl) liveEl.classList.add('d-none');
                    return;
                }

                recorder.stop();
                if (liveEl) liveEl.classList.add('d-none');
                if (statusEl) statusEl.textContent = 'Processing recording...';
            }
        </script>
    @endonce
</div>
