<div>

    {{-- ============================================================
         PERSONAL ASSISTANT – INTRO / GET STARTED MODAL
    ============================================================ --}}
    <div class="modal fade" id="paIntroModal" tabindex="-1" aria-labelledby="paIntroModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg,#17a2b8,#0062cc); color:#fff;">
                    <h5 class="modal-title" id="paIntroModalLabel">
                        <i class="fas fa-robot mr-2"></i> Your AI Personal Assistant — Get Started
                    </h5>
                    <button type="button" class="close text-white ml-auto" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">

                    {{-- Tab nav --}}
                    <ul class="nav nav-tabs nav-fill border-bottom-0 px-3 pt-3" id="paIntroTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active font-weight-bold" id="tab-what-tab" data-toggle="tab" href="#tab-what" role="tab">
                                <i class="fas fa-magic mr-1"></i> What It Can Do
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="tab-start-tab" data-toggle="tab" href="#tab-start" role="tab">
                                <i class="fas fa-play-circle mr-1"></i> Getting Started
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" id="tab-future-tab" data-toggle="tab" href="#tab-future" role="tab">
                                <i class="fas fa-lightbulb mr-1"></i> Coming Soon
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content px-4 py-3" id="paIntroTabContent">

                        {{-- Tab 1: What It Can Do --}}
                        <div class="tab-pane fade show active" id="tab-what" role="tabpanel">
                            <p class="text-muted mb-3">Your AI Personal Assistant understands <strong>voice and text commands</strong> and helps you manage your workday hands-free.</p>
                            <div class="row">
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center">
                                            <div class="mb-3" style="font-size:2.2rem; color:#17a2b8;"><i class="fas fa-microphone-alt"></i></div>
                                            <h6 class="font-weight-bold">Voice &amp; Text Commands</h6>
                                            <p class="small text-muted">Speak or type naturally. The assistant transcribes your voice, understands your intent and acts instantly.</p>
                                            <div class="text-left mt-2">
                                                <span class="badge badge-light border d-block mb-1 p-2">"Add a note about the client meeting"</span>
                                                <span class="badge badge-light border d-block mb-1 p-2">"Remind me to follow up at 3 PM"</span>
                                                <span class="badge badge-light border d-block p-2">"Create a task: send invoice Friday"</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center">
                                            <div class="mb-3" style="font-size:2.2rem; color:#28a745;"><i class="fas fa-tasks"></i></div>
                                            <h6 class="font-weight-bold">Notes, Reminders &amp; Tasks</h6>
                                            <p class="small text-muted">Instantly save notes, set time-based reminders and create to-do tasks — all in one place, searchable any time.</p>
                                            <div class="text-left mt-2">
                                                <span class="badge badge-light border d-block mb-1 p-2"><i class="fas fa-sticky-note text-warning mr-1"></i> Notes</span>
                                                <span class="badge badge-light border d-block mb-1 p-2"><i class="fas fa-bell text-danger mr-1"></i> Reminders with due times</span>
                                                <span class="badge badge-light border d-block p-2"><i class="fas fa-check-square text-success mr-1"></i> Action tasks with status</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center">
                                            <div class="mb-3" style="font-size:2.2rem; color:#6f42c1;"><i class="fas fa-user-graduate"></i></div>
                                            <h6 class="font-weight-bold">Personalised Voice Training</h6>
                                            <p class="small text-muted">Train the assistant on your accent, vocabulary and industry terms so it understands you accurately every time.</p>
                                            <div class="text-left mt-2">
                                                <span class="badge badge-light border d-block mb-1 p-2"><i class="fas fa-language text-primary mr-1"></i> Multi-language support</span>
                                                <span class="badge badge-light border d-block mb-1 p-2"><i class="fas fa-book text-info mr-1"></i> Custom vocabulary / brand names</span>
                                                <span class="badge badge-light border d-block p-2"><i class="fas fa-spell-check text-secondary mr-1"></i> Correction mapping</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center">
                                            <div class="mb-3" style="font-size:2.2rem; color:#fd7e14;"><i class="fas fa-history"></i></div>
                                            <h6 class="font-weight-bold">Interaction History</h6>
                                            <p class="small text-muted">Every command and reply is logged so you can review what was said, check decisions and pick up where you left off.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center">
                                            <div class="mb-3" style="font-size:2.2rem; color:#e83e8c;"><i class="fas fa-volume-up"></i></div>
                                            <h6 class="font-weight-bold">Text-to-Speech Replies</h6>
                                            <p class="small text-muted">The assistant can read replies aloud using XTTS or multilingual TTS providers — ideal for hands-free or eyes-free situations.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body text-center">
                                            <div class="mb-3" style="font-size:2.2rem; color:#20c997;"><i class="fas fa-file-upload"></i></div>
                                            <h6 class="font-weight-bold">Audio File Upload</h6>
                                            <p class="small text-muted">No mic? Record a voice memo on your phone and upload it directly to transcribe and run as a command.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tab 2: Getting Started --}}
                        <div class="tab-pane fade" id="tab-start" role="tabpanel">
                            <p class="text-muted mb-4">Follow these steps to get the most from your assistant. The whole setup takes about 5 minutes.</p>
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="d-flex">
                                        <div class="mr-3 flex-shrink-0">
                                            <span class="d-flex align-items-center justify-content-center rounded-circle text-white font-weight-bold" style="width:2.5rem;height:2.5rem;background:#17a2b8;font-size:1.1rem;">1</span>
                                        </div>
                                        <div>
                                            <h6 class="font-weight-bold mb-1">Set Up Your Voice Profile</h6>
                                            <p class="small text-muted mb-1">Choose your preferred language, pick a TTS provider, and add custom words the assistant should recognize — product names, client names, and abbreviations.</p>
                                            <a href="#tab-what" data-toggle="tab" class="small text-primary"><i class="fas fa-arrow-right mr-1"></i> See Voice Profile panel on the left</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="d-flex">
                                        <div class="mr-3 flex-shrink-0">
                                            <span class="d-flex align-items-center justify-content-center rounded-circle text-white font-weight-bold" style="width:2.5rem;height:2.5rem;background:#6f42c1;font-size:1.1rem;">2</span>
                                        </div>
                                        <div>
                                            <h6 class="font-weight-bold mb-1">Complete Voice Training (3 samples)</h6>
                                            <p class="small text-muted mb-1">Read each provided sentence aloud, click <strong>Start Mic → Stop → Transcribe Sample</strong>, correct any errors, then <strong>Save Correction</strong>. Repeat for all 3 samples to unlock the Assistant Console.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="d-flex">
                                        <div class="mr-3 flex-shrink-0">
                                            <span class="d-flex align-items-center justify-content-center rounded-circle text-white font-weight-bold" style="width:2.5rem;height:2.5rem;background:#28a745;font-size:1.1rem;">3</span>
                                        </div>
                                        <div>
                                            <h6 class="font-weight-bold mb-1">Give Your First Voice Command</h6>
                                            <p class="small text-muted mb-1">Once training is done, go to the <strong>Assistant Console</strong> on the right. Click <strong>Start Mic</strong>, say a command, then click <strong>Stop → Transcribe → Run Command</strong>.</p>
                                            <div class="mt-2">
                                                <span class="badge badge-secondary p-2 mr-1">"Remind me at 2 PM to call supplier"</span>
                                                <span class="badge badge-secondary p-2">"Add note: client wants delivery by Monday"</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="d-flex">
                                        <div class="mr-3 flex-shrink-0">
                                            <span class="d-flex align-items-center justify-content-center rounded-circle text-white font-weight-bold" style="width:2.5rem;height:2.5rem;background:#fd7e14;font-size:1.1rem;">4</span>
                                        </div>
                                        <div>
                                            <h6 class="font-weight-bold mb-1">Review Your Saved Items</h6>
                                            <p class="small text-muted mb-1">All your notes, reminders and tasks appear in the <strong>Saved Items</strong> panel on the right. Check due dates and statuses there.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="d-flex">
                                        <div class="mr-3 flex-shrink-0">
                                            <span class="d-flex align-items-center justify-content-center rounded-circle text-white font-weight-bold" style="width:2.5rem;height:2.5rem;background:#e83e8c;font-size:1.1rem;">5</span>
                                        </div>
                                        <div>
                                            <h6 class="font-weight-bold mb-1">No Microphone? Use Audio File Upload</h6>
                                            <p class="small text-muted mb-1">Record a voice note on your mobile and click <strong>Use Audio File</strong> to upload and transcribe it. Works for both training samples and commands.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="d-flex">
                                        <div class="mr-3 flex-shrink-0">
                                            <span class="d-flex align-items-center justify-content-center rounded-circle text-white font-weight-bold" style="width:2.5rem;height:2.5rem;background:#20c997;font-size:1.1rem;">6</span>
                                        </div>
                                        <div>
                                            <h6 class="font-weight-bold mb-1">Type Commands Too</h6>
                                            <p class="small text-muted mb-1">Prefer typing? Use <strong>Manual Command Input</strong> in the Assistant Console — just type your command and hit <strong>Run Command</strong> without any microphone.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-light border mt-2">
                                <strong><i class="fas fa-terminal mr-1 text-dark"></i> Sample commands you can try right now:</strong>
                                <div class="row mt-2">
                                    <div class="col-sm-6">
                                        <ul class="small mb-0 pl-3">
                                            <li>"Add note: project budget approved"</li>
                                            <li>"Remind me tomorrow at 9 AM to submit report"</li>
                                            <li>"Create task: follow up with client by Friday"</li>
                                            <li>"Find my recent notes about the diagnostic clinic"</li>
                                        </ul>
                                    </div>
                                    <div class="col-sm-6">
                                        <ul class="small mb-0 pl-3">
                                            <li>"Schedule a reminder for next Monday at 10 AM"</li>
                                            <li>"Note that our monthly plan starts at twelve dollars"</li>
                                            <li>"Add a task to review invoices before Friday evening"</li>
                                            <li>"Send an update email to our client about pending proposal"</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tab 3: Coming Soon --}}
                        <div class="tab-pane fade" id="tab-future" role="tabpanel">
                            <p class="text-muted mb-3">Here is what's planned next to make this a complete AI productivity tool for business owners.</p>
                            <div class="row">

                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card border-left-primary h-100 shadow-sm" style="border-left:4px solid #0062cc !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-calendar-alt text-primary mr-2" style="font-size:1.4rem;"></i>
                                                <h6 class="font-weight-bold mb-0">Smart Calendar &amp; Scheduling</h6>
                                            </div>
                                            <p class="small text-muted mb-0">Connect Google / Outlook calendar. Say "Schedule a meeting with Priya next Tuesday at 2 PM" and it books it automatically with conflict detection.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm" style="border-left:4px solid #28a745 !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-envelope-open-text text-success mr-2" style="font-size:1.4rem;"></i>
                                                <h6 class="font-weight-bold mb-0">Email Drafting &amp; Sending</h6>
                                            </div>
                                            <p class="small text-muted mb-0">Dictate emails hands-free. "Draft an email to Rajesh confirming tomorrow's meeting and send it" — the AI composes and dispatches.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm" style="border-left:4px solid #fd7e14 !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-file-invoice-dollar text-warning mr-2" style="font-size:1.4rem;"></i>
                                                <h6 class="font-weight-bold mb-0">Invoice &amp; Expense Assistant</h6>
                                            </div>
                                            <p class="small text-muted mb-0">Say "Create invoice for ABC Pharma for ₹45,000 — Lab Tests + Consultation" and the system generates a PDF invoice ready to send.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm" style="border-left:4px solid #6f42c1 !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-chart-line text-purple mr-2" style="font-size:1.4rem; color:#6f42c1;"></i>
                                                <h6 class="font-weight-bold mb-0">Business Analytics Briefing</h6>
                                            </div>
                                            <p class="small text-muted mb-0">Each morning ask "What's my sales summary for this week?" and get an AI-narrated briefing of leads, revenue, chat volume and top queries.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm" style="border-left:4px solid #e83e8c !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-users text-danger mr-2" style="font-size:1.4rem; color:#e83e8c;"></i>
                                                <h6 class="font-weight-bold mb-0">CRM &amp; Lead Follow-up</h6>
                                            </div>
                                            <p class="small text-muted mb-0">Voice-log follow-up notes against leads. "Update lead Anita Sharma — called today, interested in Gold plan, call back Wednesday." Synced to your CRM.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm" style="border-left:4px solid #17a2b8 !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-whatsapp text-info mr-2" style="font-size:1.4rem; color:#25D366;"></i>
                                                <h6 class="font-weight-bold mb-0">WhatsApp &amp; SMS Integration</h6>
                                            </div>
                                            <p class="small text-muted mb-0">Send WhatsApp messages or SMS by voice. "Send WhatsApp to +91 98765 43210: Your test results are ready for collection."</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm" style="border-left:4px solid #20c997 !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-file-alt text-teal mr-2" style="font-size:1.4rem; color:#20c997;"></i>
                                                <h6 class="font-weight-bold mb-0">Document Dictation &amp; Summary</h6>
                                            </div>
                                            <p class="small text-muted mb-0">Dictate reports, meeting minutes or SOP notes. Summarise uploaded PDFs by asking "What are the key points in this document?"</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm" style="border-left:4px solid #dc3545 !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-mobile-alt text-danger mr-2" style="font-size:1.4rem;"></i>
                                                <h6 class="font-weight-bold mb-0">Mobile App / PWA</h6>
                                            </div>
                                            <p class="small text-muted mb-0">A mobile-optimised progressive web app so you can speak commands while on-site at a client, in a clinic, or on the go — without a laptop.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm" style="border-left:4px solid #6c757d !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-bell text-secondary mr-2" style="font-size:1.4rem;"></i>
                                                <h6 class="font-weight-bold mb-0">Smart Reminder Notifications</h6>
                                            </div>
                                            <p class="small text-muted mb-0">Browser push notifications and email alerts when a saved reminder is due — so you never miss a follow-up, deadline or appointment.</p>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>{{-- end tab-content --}}
                </div>

                <div class="modal-footer justify-content-between">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="paDontShowAgain">
                        <label class="custom-control-label text-muted small" for="paDontShowAgain">Don't show this again</label>
                    </div>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">
                        <i class="fas fa-rocket mr-1"></i> Let's Get Started
                    </button>
                </div>
            </div>
        </div>
    </div>
    {{-- / intro modal --}}

    {{-- Page header bar with help button --}}
    <div class="d-flex align-items-center justify-content-between mb-3 p-3 rounded" style="background:linear-gradient(135deg,#e8f4fd,#d1ecf1);">
        <div>
            <h5 class="mb-1 text-dark font-weight-bold"><i class="fas fa-robot mr-2 text-info"></i> AI Personal Assistant</h5>
            <p class="mb-0 small text-muted">Voice &amp; text commands · Notes · Reminders · Tasks · Voice training</p>
        </div>
        <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#paIntroModal">
            <i class="fas fa-question-circle mr-1"></i> What can this do?
        </button>
    </div>

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

            // Auto-show intro modal on first visit
            document.addEventListener('DOMContentLoaded', function () {
                if (!localStorage.getItem('pa_intro_seen')) {
                    var modal = new bootstrap.Modal(document.getElementById('paIntroModal'), {});
                    // Fallback for AdminLTE (jQuery)
                    if (typeof $ !== 'undefined') {
                        $('#paIntroModal').modal('show');
                    } else {
                        modal.show();
                    }
                }

                // Mark as seen when modal is hidden
                document.getElementById('paIntroModal').addEventListener('hidden.bs.modal', function () {
                    if (document.getElementById('paDontShowAgain').checked) {
                        localStorage.setItem('pa_intro_seen', '1');
                    }
                });
                // Support jQuery modal hidden event too
                if (typeof $ !== 'undefined') {
                    $('#paIntroModal').on('hidden.bs.modal', function () {
                        if (document.getElementById('paDontShowAgain').checked) {
                            localStorage.setItem('pa_intro_seen', '1');
                        }
                    });
                }
            });
        </script>
    @endonce
</div>
