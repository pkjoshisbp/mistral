<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-comment-dots"></i> Personal Assistant Widget</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Assistant Widget</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <strong><i class="fas fa-robot"></i> Ask from Extended Memory (Typing + Voice)</strong>
                        </div>
                        <div class="card-body" style="min-height: 420px; max-height: 520px; overflow-y: auto;" id="assistantWidgetHistory">
                            @forelse($history as $entry)
                                <div class="mb-3">
                                    <div class="p-2 rounded bg-light border">
                                        <strong>You:</strong> {{ $entry['question'] }}
                                    </div>
                                    <div class="p-2 rounded bg-white border mt-1">
                                        <strong>Assistant:</strong> {{ $entry['answer'] }}
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">
                                    Ask a question to test your private Extended Memory knowledge base.
                                </div>
                            @endforelse
                        </div>
                        <div class="card-footer">
                            <form wire:submit.prevent="ask">
                                <label>Message</label>
                                <textarea id="paWidgetInput" wire:model="message" rows="2" class="form-control" placeholder="Type your question, or use Voice Input"></textarea>
                                @error('message') <small class="text-danger">{{ $message }}</small> @enderror
                                <div class="d-flex justify-content-between mt-2">
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="paVoiceBtn">
                                            <i class="fas fa-microphone"></i> Voice Input
                                        </button>
                                        <small id="paVoiceStatus" class="text-muted ml-2">Mic idle.</small>
                                        <div id="paVoiceConfirm" class="alert alert-light border mt-2 mb-0 py-2 px-2 d-none">
                                            <small class="d-block text-muted">I heard:</small>
                                            <strong id="paVoicePreview" class="d-block"></strong>
                                            <div class="mt-2 d-flex align-items-center">
                                                <button type="button" class="btn btn-success btn-xs mr-1" id="paProceedBtn">Proceed</button>
                                                <button type="button" class="btn btn-warning btn-xs mr-1" id="paCorrectBtn">Correct</button>
                                                <button type="button" class="btn btn-danger btn-xs" id="paStopBtn">Stop</button>
                                                <span id="paProceedSpinner" class="spinner-border spinner-border-sm text-primary ml-2 d-none" role="status" aria-hidden="true"></span>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted mr-2">Wake word:</small>
                                            <input type="text" id="paWakeWord" class="form-control form-control-sm d-inline-block" style="width: 170px;" placeholder="assistant">
                                            <small class="text-muted ml-2">Say: "hey [wake word]"</small>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i> Ask</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        (function () {
            if (window.__paVoiceWidgetInitialized) {
                return;
            }
            window.__paVoiceWidgetInitialized = true;

            let recognition = null;
            let isListening = false;
            let shouldKeepListening = false;
            let manualStop = false;
            let restartTimer = null;
            let proceedTimer = null;
            let pendingTranscript = '';
            let awaitingConfirmation = false;
            let isConfirmAudioPlaying = false;
            let isCorrectionMode = false;
            const AUTO_PROCEED_DELAY_MS = 8000;
            const WAKE_WORD_KEY = 'pa_widget_wake_word';

            const getElements = function () {
                return {
                    voiceBtn: document.getElementById('paVoiceBtn'),
                    input: document.getElementById('paWidgetInput'),
                    status: document.getElementById('paVoiceStatus'),
                    wakeWord: document.getElementById('paWakeWord'),
                    confirmBox: document.getElementById('paVoiceConfirm'),
                    preview: document.getElementById('paVoicePreview'),
                    proceedBtn: document.getElementById('paProceedBtn'),
                    correctBtn: document.getElementById('paCorrectBtn'),
                    stopBtn: document.getElementById('paStopBtn'),
                    proceedSpinner: document.getElementById('paProceedSpinner'),
                };
            };

            const normalizeText = function (text) {
                return (text || '').toString().trim().toLowerCase();
            };

            const escapeRegExp = function (text) {
                return (text || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            };

            const getWakeWord = function () {
                const { wakeWord } = getElements();
                const value = normalizeText(wakeWord ? wakeWord.value : '');
                return value || 'assistant';
            };

            const persistWakeWord = function () {
                const wakeWord = getWakeWord();
                try {
                    localStorage.setItem(WAKE_WORD_KEY, wakeWord);
                } catch (e) {
                    // ignore storage errors
                }
            };

            const hydrateWakeWord = function () {
                const { wakeWord } = getElements();
                if (!wakeWord) {
                    return;
                }

                let savedWakeWord = '';
                try {
                    savedWakeWord = normalizeText(localStorage.getItem(WAKE_WORD_KEY) || '');
                } catch (e) {
                    savedWakeWord = '';
                }

                wakeWord.value = savedWakeWord || 'assistant';
            };

            const setIdleState = function () {
                const { voiceBtn, status } = getElements();
                if (status) {
                    status.textContent = 'Mic idle.';
                }
                if (voiceBtn) {
                    voiceBtn.classList.add('btn-outline-secondary');
                    voiceBtn.classList.remove('btn-danger');
                }
            };

            const stopProceedTimer = function () {
                if (proceedTimer) {
                    clearTimeout(proceedTimer);
                    proceedTimer = null;
                }
            };

            const hideConfirmation = function () {
                const { confirmBox, preview, proceedSpinner } = getElements();
                stopProceedTimer();
                if (proceedSpinner) {
                    proceedSpinner.classList.add('d-none');
                }
                if (preview) {
                    preview.textContent = '';
                }
                if (confirmBox) {
                    confirmBox.classList.add('d-none');
                }
                pendingTranscript = '';
                awaitingConfirmation = false;
                isConfirmAudioPlaying = false;
            };

            const enterCorrectionMode = function () {
                const { status } = getElements();
                hideConfirmation();
                isCorrectionMode = true;
                if (status) {
                    status.textContent = 'Correction mode: speak corrected question now, or edit manually and click Ask.';
                }
                speakFeedback('Correction mode enabled. Speak corrected question now, or edit manually and click ask.');
            };

            const showConfirmation = function (text) {
                const { confirmBox, preview, proceedSpinner } = getElements();
                if (preview) {
                    preview.textContent = text;
                }
                if (confirmBox) {
                    confirmBox.classList.remove('d-none');
                }
                if (proceedSpinner) {
                    proceedSpinner.classList.remove('d-none');
                }
            };

            const speakFeedback = function (text) {
                if (!window.speechSynthesis || !window.SpeechSynthesisUtterance) {
                    return null;
                }

                try {
                    window.speechSynthesis.cancel();
                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.rate = 1;
                    utterance.pitch = 1;
                    window.speechSynthesis.speak(utterance);
                    return utterance;
                } catch (e) {
                    // ignore speech synthesis errors
                    return null;
                }
            };

            const syncInputAndLivewire = function (text) {
                const { input } = getElements();
                if (!input) {
                    return null;
                }

                input.value = text;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));

                const componentRoot = input.closest('[wire\\:id]');
                const componentId = componentRoot ? componentRoot.getAttribute('wire:id') : null;
                if (!componentId || !window.Livewire || typeof window.Livewire.find !== 'function') {
                    return null;
                }

                return window.Livewire.find(componentId);
            };

            const submitPendingTranscript = function () {
                if (!pendingTranscript) {
                    return;
                }

                const { status } = getElements();
                const component = syncInputAndLivewire(pendingTranscript);

                if (component && typeof component.call === 'function') {
                    component.call('ask');
                }

                if (status) {
                    status.textContent = 'Voice message sent.';
                }

                hideConfirmation();
                isCorrectionMode = false;
            };

            const startProceedWindow = function () {
                const { status, proceedSpinner } = getElements();
                stopProceedTimer();
                if (proceedSpinner) {
                    proceedSpinner.classList.remove('d-none');
                }
                if (status) {
                    status.textContent = 'Proceeding in 8 seconds... say stop to cancel.';
                }

                proceedTimer = setTimeout(function () {
                    if (!awaitingConfirmation || !pendingTranscript) {
                        return;
                    }
                    if (isConfirmAudioPlaying) {
                        startProceedWindow();
                        return;
                    }
                    submitPendingTranscript();
                }, AUTO_PROCEED_DELAY_MS);
            };

            const isProceedCommand = function (text) {
                return /^(okay|ok|proceed|confirm|yes|send)$/i.test(normalizeText(text));
            };

            const isStopCommand = function (text) {
                return /^(stop|cancel|wait|hold)$/i.test(normalizeText(text));
            };

            const isCorrectCommand = function (text) {
                return /^(correct|correction|edit|manual|change)$/i.test(normalizeText(text));
            };

            const askForConfirmation = function (transcript) {
                const { status } = getElements();

                pendingTranscript = transcript;
                awaitingConfirmation = true;
                isCorrectionMode = false;
                showConfirmation(transcript);
                const utterance = speakFeedback('I heard: ' + transcript + '. Say okay to proceed, stop to cancel, or correct to edit.');

                if (utterance) {
                    isConfirmAudioPlaying = true;
                    utterance.onend = function () {
                        isConfirmAudioPlaying = false;
                        if (awaitingConfirmation) {
                            startProceedWindow();
                        }
                    };
                    utterance.onerror = function () {
                        isConfirmAudioPlaying = false;
                        if (awaitingConfirmation) {
                            startProceedWindow();
                        }
                    };
                } else {
                    isConfirmAudioPlaying = false;
                    startProceedWindow();
                }

                if (status) {
                    status.textContent = 'Please confirm: say okay, stop, or correct.';
                }
            };

            const parseWakeWordCommand = function (transcript) {
                const wakeWord = getWakeWord();
                const escapedWakeWord = escapeRegExp(wakeWord);
                const wakeRegex = new RegExp('^(hey|hi|ok|okay)\\s+' + escapedWakeWord + '[,\\s:;-]*(.*)$', 'i');
                const match = transcript.match(wakeRegex);
                if (!match) {
                    return null;
                }

                return {
                    usedWakeWord: true,
                    remainder: (match[2] || '').trim(),
                };
            };

            const ensureRecognition = function () {
                if (recognition) {
                    return recognition;
                }

                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRecognition) {
                    const { voiceBtn, status } = getElements();
                    if (status) {
                        status.textContent = 'Voice input not supported in this browser.';
                    }
                    if (voiceBtn) {
                        voiceBtn.disabled = true;
                    }
                    return null;
                }

                recognition = new SpeechRecognition();
                recognition.lang = (navigator.language || 'en-IN');
                recognition.interimResults = false;
                recognition.maxAlternatives = 1;
                recognition.continuous = false;

                recognition.onstart = function () {
                    isListening = true;
                    const { voiceBtn, status } = getElements();
                    if (status) {
                        status.textContent = 'Listening... say your question. You can also start with "hey ' + getWakeWord() + '".';
                    }
                    if (voiceBtn) {
                        voiceBtn.classList.remove('btn-outline-secondary');
                        voiceBtn.classList.add('btn-danger');
                    }
                };

                recognition.onresult = function (event) {
                    let transcript = '';
                    for (let index = event.resultIndex; index < event.results.length; index++) {
                        const result = event.results[index];
                        if (!result || !result.isFinal) {
                            continue;
                        }
                        transcript = (result[0]?.transcript || '').trim();
                    }

                    transcript = transcript.trim();
                    if (!transcript) {
                        return;
                    }

                    if (awaitingConfirmation) {
                        if (isStopCommand(transcript)) {
                            const { status } = getElements();
                            hideConfirmation();
                            if (status) {
                                status.textContent = 'Cancelled. You can speak again.';
                            }
                            speakFeedback('Cancelled.');
                            isCorrectionMode = false;
                            return;
                        }

                        if (isProceedCommand(transcript)) {
                            submitPendingTranscript();
                            return;
                        }

                        if (isCorrectCommand(transcript)) {
                            enterCorrectionMode();
                            return;
                        }

                        askForConfirmation(transcript);
                        return;
                    }

                    if (isCorrectionMode) {
                        if (isStopCommand(transcript)) {
                            const { status } = getElements();
                            isCorrectionMode = false;
                            if (status) {
                                status.textContent = 'Correction cancelled.';
                            }
                            speakFeedback('Correction cancelled.');
                            return;
                        }

                        if (isProceedCommand(transcript)) {
                            const { input, status } = getElements();
                            const manualText = (input && input.value ? input.value : '').trim();
                            if (!manualText) {
                                if (status) {
                                    status.textContent = 'No corrected text found. Please speak or type correction first.';
                                }
                                return;
                            }
                            pendingTranscript = manualText;
                            submitPendingTranscript();
                            return;
                        }

                        isCorrectionMode = false;
                        syncInputAndLivewire(transcript);
                        askForConfirmation(transcript);
                        return;
                    }

                    const wakeParsed = parseWakeWordCommand(transcript);
                    if (wakeParsed) {
                        transcript = wakeParsed.remainder;
                        if (!transcript) {
                            const { status } = getElements();
                            if (status) {
                                status.textContent = 'Wake word detected. Now say your question.';
                            }
                            return;
                        }
                    }

                    syncInputAndLivewire(transcript);
                    askForConfirmation(transcript);
                };

                recognition.onend = function () {
                    isListening = false;

                    if (manualStop) {
                        manualStop = false;
                        shouldKeepListening = false;
                        setIdleState();
                        return;
                    }

                    if (shouldKeepListening) {
                        if (restartTimer) {
                            clearTimeout(restartTimer);
                        }
                        restartTimer = setTimeout(function () {
                            try {
                                recognition.start();
                            } catch (error) {
                                shouldKeepListening = false;
                                setIdleState();
                            }
                        }, 300);
                        return;
                    }

                    setIdleState();
                };

                recognition.onerror = function (event) {
                    const { status } = getElements();
                    if (status) {
                        const errorCode = event && event.error ? event.error : 'unknown';
                        if (errorCode === 'not-allowed' || errorCode === 'service-not-allowed') {
                            status.textContent = 'Microphone permission denied. Allow mic in browser settings.';
                        } else if (errorCode === 'no-speech') {
                            status.textContent = 'No speech detected. Still listening...';
                        } else {
                            status.textContent = 'Voice input failed (' + errorCode + ').';
                        }
                    }

                    if (event && (event.error === 'not-allowed' || event.error === 'service-not-allowed')) {
                        shouldKeepListening = false;
                    }

                    isListening = false;
                };

                return recognition;
            };

            document.addEventListener('click', function (event) {
                const button = event.target.closest('#paVoiceBtn');
                if (!button) {
                    return;
                }

                const { input, status } = getElements();
                if (!input || !status) {
                    return;
                }

                const recognizer = ensureRecognition();
                if (!recognizer) {
                    return;
                }

                if (isListening) {
                    manualStop = true;
                    shouldKeepListening = false;
                    recognizer.stop();
                    return;
                }

                try {
                    hideConfirmation();
                    shouldKeepListening = true;
                    isCorrectionMode = false;
                    recognizer.start();
                } catch (error) {
                    status.textContent = 'Unable to start voice input. Please try again.';
                    isListening = false;
                    shouldKeepListening = false;
                    setIdleState();
                }
            });

            document.addEventListener('input', function (event) {
                if (event.target && event.target.id === 'paWakeWord') {
                    persistWakeWord();
                }
            });

            document.addEventListener('click', function (event) {
                const { proceedBtn, correctBtn, stopBtn, status } = getElements();

                if (proceedBtn && event.target.closest('#paProceedBtn')) {
                    submitPendingTranscript();
                    return;
                }

                if (correctBtn && event.target.closest('#paCorrectBtn')) {
                    enterCorrectionMode();
                    return;
                }

                if (stopBtn && event.target.closest('#paStopBtn')) {
                    hideConfirmation();
                    if (status) {
                        status.textContent = 'Cancelled. Listening continues.';
                    }
                    speakFeedback('Cancelled.');
                    isCorrectionMode = false;
                }
            });

            hydrateWakeWord();

            setIdleState();
        })();
    </script>
</div>
