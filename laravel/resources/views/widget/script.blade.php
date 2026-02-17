(function() {
    'use strict';
    
    // Widget Configuration
    const config = @json($widgetConfig);
    
    // Prevent multiple initializations across orgs
    window.__AiChatWidgetInstances = window.__AiChatWidgetInstances || {};
    const __ORG_KEY__ = (typeof config === 'object' && config.orgId) ? String(config.orgId) : 'default';
    if (window.__AiChatWidgetInstances[__ORG_KEY__]) {
        return;
    }

    class AiChatWidget {
        constructor(config) {
            this.config = config;
            this.isOpen = false;
            this.isExpanded = false;
            this.chatHistoryTtlHours = Number(this.config.chatHistoryTtlHours ?? 24);
            this.agentPollingIntervalMs = Number(this.config.agentPollingIntervalMs ?? 20000);
            this.isPollingInFlight = false;
            // ISSUE 5B FIX: Persist session across page navigation
            this.sessionId = this.getOrCreateSessionId();
            this.messages = [];
            this.lastAgentMessageId = 0;
            this.agentPoller = null;
            this.welcomeShown = false;
            this.leadCaptured = this.checkLeadCaptured();
            this.userInfo = this.loadUserInfo();
            if (this.config.requireContactForGuests && !this.hasValidRequiredContact(this.userInfo)) {
                this.leadCaptured = false;
            }
            this.locationInfo = {};
            this.ws = null;
            this.wsReady = false;
            this.wsConnecting = false;
            this.wsReconnectAttempts = 0;
            this.wsBusy = false;
            this.wsStream = null;
            this.wsPingTimer = null;
            this.wsShouldReconnect = true;
            this.init();
            this.detectLocation();
            // ISSUE 5B FIX: Load previous messages after init
            this.loadPersistedMessages();
            this.syncSessionFromStoredContact();
            if (this.config?.scriptVersion) {
                console.info('[AI Widget] Script version:', this.config.scriptVersion);
            }
        }

        checkLeadCaptured() {
            // Check if lead was already captured for this organization
            const key = `ai_lead_captured_${this.config.orgId}`;
            return localStorage.getItem(key) === 'true';
        }

        loadUserInfo() {
            // Load previously captured user info
            const key = `ai_user_info_${this.config.orgId}`;
            const stored = localStorage.getItem(key);
            return stored ? JSON.parse(stored) : {};
        }

        saveLeadCaptured() {
            // Save lead captured status
            const key = `ai_lead_captured_${this.config.orgId}`;
            localStorage.setItem(key, 'true');
        }

        showWelcomeMessage(customMessage = null) {
            // Only show welcome message once per session
            if (this.welcomeShown) {
                return;
            }
            
            this.welcomeShown = true;
            const message = customMessage || this.config.welcomeMessage;
            
            setTimeout(() => {
                this.addMessage(message, 'bot');
            }, 500);
        }

        saveUserInfo() {
            // Save user info for future sessions
            const key = `ai_user_info_${this.config.orgId}`;
            localStorage.setItem(key, JSON.stringify(this.userInfo));
        }

        hasValidRequiredContact(userInfo) {
            const info = userInfo || {};
            const email = String(info.email || '').trim();
            const phone = String(info.phone || '').trim();
            const emailRegex = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i;
            const phoneRegex = /^\+?[0-9][0-9\-\s()]{6,19}$/;
            const phoneDigits = (phone.match(/\d/g) || []).length;
            return emailRegex.test(email) && phoneRegex.test(phone) && phoneDigits >= 7 && phoneDigits <= 15;
        }

        // ISSUE 5B FIX: Get or create persistent session ID
        getOrCreateSessionId() {
            const key = `ai_session_id_${this.config.orgId}`;
            const timestampKey = `ai_session_timestamp_${this.config.orgId}`;
            
            let sessionId = sessionStorage.getItem(key);
            const lastActivity = sessionStorage.getItem(timestampKey);
            const now = Date.now();
            
            // Session expires after 30 minutes of inactivity
            if (sessionId && lastActivity && (now - parseInt(lastActivity)) < 30 * 60 * 1000) {
                // Valid session - update timestamp
                sessionStorage.setItem(timestampKey, now.toString());
                return sessionId;
            }
            
            // Create new session
            sessionId = 'session_' + Math.random().toString(36).substr(2, 9) + '_' + now;
            sessionStorage.setItem(key, sessionId);
            sessionStorage.setItem(timestampKey, now.toString());
            
            return sessionId;
        }

        generateSessionId() {
            // Deprecated - use getOrCreateSessionId instead
            return this.getOrCreateSessionId();
        }

        setSessionId(sessionId) {
            if (!sessionId || typeof sessionId !== 'string') {
                return;
            }

            this.sessionId = sessionId;
            const key = `ai_session_id_${this.config.orgId}`;
            const timestampKey = `ai_session_timestamp_${this.config.orgId}`;
            sessionStorage.setItem(key, this.sessionId);
            sessionStorage.setItem(timestampKey, Date.now().toString());
        }

        async syncSessionFromStoredContact() {
            const email = String(this.userInfo?.email || '').trim();
            if (!email) {
                return;
            }

            const phone = String(this.userInfo?.phone || '').trim();

            try {
                const params = new URLSearchParams();
                params.set('email', email);
                if (phone) {
                    params.set('phone', phone);
                }
                params.set('limit', '30');

                const response = await fetch(`${this.config.apiUrl}/widget/${this.config.orgId}/history?${params.toString()}`, {
                    method: 'GET'
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                if (!data || !data.session_id || !Array.isArray(data.messages) || data.messages.length === 0) {
                    return;
                }

                if (data.session_id === this.sessionId && this.messages.length > 0) {
                    return;
                }

                this.setSessionId(data.session_id);
                this.messages = [];
                this.lastAgentMessageId = 0;

                const container = document.getElementById(this.ids.messages);
                if (container) {
                    container.innerHTML = '';
                }

                data.messages.forEach(msg => {
                    const sender = msg.sender || 'bot';
                    const content = msg.message || '';
                    const senderName = msg.sender_name || null;
                    const sentAt = msg.sent_at || null;

                    this.renderMessage(content, sender, sentAt, senderName);
                    this.messages.push({
                        content,
                        sender,
                        senderName,
                        messageId: msg.id || null,
                        timestamp: sentAt ? new Date(sentAt) : new Date(),
                    });

                    if (sender === 'agent' && msg.id) {
                        this.lastAgentMessageId = Math.max(this.lastAgentMessageId, msg.id);
                    }
                });

                this.welcomeShown = this.messages.length > 0;
                this.saveMessages();
            } catch (error) {
                console.debug('[AI Widget] Session sync by contact failed:', error);
            }
        }

        // ISSUE 5B FIX: Save messages to localStorage
        saveMessages() {
            if (this.chatHistoryTtlHours <= 0) {
                return;
            }
            const key = `ai_chat_messages_${this.config.orgId}_${this.sessionId}`;
            try {
                localStorage.setItem(key, JSON.stringify({
                    messages: this.messages,
                    savedAt: Date.now()
                }));
            } catch (e) {
                console.warn('[AI Chat] Failed to save messages:', e);
            }
        }

        // ISSUE 5B FIX: Load messages from localStorage
        loadPersistedMessages() {
            const key = `ai_chat_messages_${this.config.orgId}_${this.sessionId}`;
            try {
                const stored = localStorage.getItem(key);
                if (stored) {
                    const parsed = JSON.parse(stored);
                    const payload = Array.isArray(parsed)
                        ? { messages: parsed, savedAt: Date.now() }
                        : parsed;

                    const messages = payload?.messages;
                    const savedAt = payload?.savedAt;
                    const ttlMs = this.chatHistoryTtlHours > 0
                        ? this.chatHistoryTtlHours * 60 * 60 * 1000
                        : 0;

                    if (ttlMs > 0 && savedAt && (Date.now() - savedAt) > ttlMs) {
                        localStorage.removeItem(key);
                        return;
                    }

                    if (Array.isArray(messages) && messages.length > 0) {
                        this.messages = messages;
                        this.welcomeShown = true; // Don't show welcome if restoring messages
                        // Render all stored messages
                        messages.forEach(msg => {
                            this.renderMessage(msg.content, msg.sender, msg.timestamp, msg.senderName);
                            if (msg.sender === 'agent' && msg.messageId) {
                                this.lastAgentMessageId = Math.max(this.lastAgentMessageId, msg.messageId);
                            }
                        });

                        if (Array.isArray(parsed)) {
                            this.saveMessages();
                        }
                    }
                }
            } catch (e) {
                console.warn('[AI Chat] Failed to load messages:', e);
            }
        }

        // ISSUE 5B FIX: Clear old session data
        clearOldSessions() {
            const prefix = `ai_chat_messages_${this.config.orgId}_`;
            const keys = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith(prefix) && !key.includes(this.sessionId)) {
                    keys.push(key);
                }
            }
            // Clear old sessions (keep only current)
            keys.forEach(key => localStorage.removeItem(key));
        }

        init() {
            this.loadStyles();
            this.createWidget();
            this.bindEvents();
            
            // Detect and apply Shopify theme colors if available
            if (this.config.isShopify) {
                setTimeout(() => {
                    const colors = this.detectShopifyThemeColors();
                    if (colors) {
                        this.applyDynamicColors(colors);
                    }
                }, 500); // Wait for page to fully render
            }
        }

        loadStyles() {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = `${this.config.apiUrl}/widget/${this.config.orgId}/styles.css`;
            document.head.appendChild(link);
        }

        createWidget() {
            // Remove any existing widget instances first
            const existingWidget = document.getElementById('ai-chat-widget');
            if (existingWidget) {
                existingWidget.remove();
            }
            
            // Generate unique IDs to prevent conflicts
            const widgetId = 'ai-chat-widget-' + this.config.orgId;
            const buttonId = 'ai-chat-button-' + this.config.orgId;
            const windowId = 'ai-chat-window-' + this.config.orgId;
            const closeId = 'ai-chat-close-' + this.config.orgId;
            const expandId = 'ai-chat-expand-' + this.config.orgId;
            const messagesId = 'ai-chat-messages-' + this.config.orgId;
            const inputId = 'ai-chat-input-' + this.config.orgId;
            const sendId = 'ai-chat-send-' + this.config.orgId;
            const notificationId = 'ai-chat-notification-' + this.config.orgId;
            const leadFormId = 'ai-chat-lead-form-' + this.config.orgId;
            const leadNameId = 'ai-lead-name-' + this.config.orgId;
            const leadEmailId = 'ai-lead-email-' + this.config.orgId;
            const leadPhoneId = 'ai-lead-phone-' + this.config.orgId;
            const leadSubmitId = 'ai-chat-lead-submit-' + this.config.orgId;
            const leadSkipId = 'ai-chat-lead-skip-' + this.config.orgId;
            
            // Store IDs for later use
            this.ids = {
                widget: widgetId,
                button: buttonId,
                window: windowId,
                close: closeId,
                expand: expandId,
                messages: messagesId,
                input: inputId,
                send: sendId,
                notification: notificationId,
                leadForm: leadFormId,
                leadName: leadNameId,
                leadEmail: leadEmailId,
                leadPhone: leadPhoneId,
                leadSubmit: leadSubmitId,
                leadSkip: leadSkipId
            };
            
            // Create widget container
            const widgetHTML = `
                <div id="${widgetId}" class="ai-chat-widget ${this.config.position}" style="--ai-offset-x: ${this.config.offsetX || 20}px; --ai-offset-y: ${this.config.offsetY || 20}px;">
                    <!-- Chat Button -->
                    <div id="${buttonId}" class="ai-chat-button">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z" fill="white"/>
                            <circle cx="8" cy="10" r="1" fill="white"/>
                            <circle cx="12" cy="10" r="1" fill="white"/>
                            <circle cx="16" cy="10" r="1" fill="white"/>
                        </svg>
                        <span class="ai-chat-notification" id="${notificationId}">1</span>
                    </div>

                    ${this.config.brandingEnabled && this.config.brandingBadge ? `
                        <div class="ai-chat-badge" style="position:absolute; ${this.config.position.includes('bottom') ? 'bottom: -20px;' : 'top: -20px;'} ${this.config.position.includes('right') ? 'right: 0;' : 'left: 0;'} opacity: 0.7;">
                            <a href="https://ai-chat.support" target="_blank" rel="nofollow noopener noreferrer" aria-label="Powered by ai chat" style="display:inline-flex;align-items:center;text-decoration:none; font-size:11px; color:#111; font-family: 'Segoe UI', Helvetica, Arial, sans-serif;">
                                Powered by ai chat
                            </a>
                        </div>
                    ` : ''}

                    <!-- Chat Window -->
                    <div id="${windowId}" class="ai-chat-window" style="display: none;">
                        <!-- Header -->
                        <div class="ai-chat-header">
                            ${this.config.showHeaderLogo && this.config.headerLogoUrl ? `
                                <div class="ai-chat-logo">
                                    <img src="${this.config.headerLogoUrl}" alt="${this.config.orgName} logo" onerror="this.style.display='none'" />
                                </div>
                            ` : ''}
                            <div class="ai-chat-header-info">
                                <div class="ai-chat-title">${this.config.orgName}</div>
                                <div class="ai-chat-status">
                                    <span class="ai-chat-status-dot"></span>
                                    Online
                                </div>
                            </div>
                            <div class="ai-chat-header-actions">
                                <button id="${this.ids.expand}" class="ai-chat-expand" title="Expand chat">
                                    <svg width="18" height="18" viewBox="0 0 18 18">
                                        <path d="M3 3h4v2H5v2H3V3zm8 0h4v4h-2V5h-2V3zM3 11v4h4v-2H5v-2H3zm10 0v2h-2v2h4v-4h-2z" fill="currentColor"/>
                                    </svg>
                                </button>
                                <button id="${closeId}" class="ai-chat-close" title="Close chat">
                                    <svg width="20" height="20" viewBox="0 0 20 20">
                                        <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Messages -->
                        <div id="${messagesId}" class="ai-chat-messages">
                        </div>

                        <!-- Lead Capture Form -->
                        <div id="${leadFormId}" class="ai-chat-lead-form" style="display: none;">
                            <div class="ai-chat-lead-content">
                                <h3>Let's get started!</h3>
                                <p>Please provide your details so we can assist you better:</p>
                                <div class="ai-chat-form-group">
                                    <input type="text" id="${leadNameId}" class="ai-chat-form-input" placeholder="Your Name *" required />
                                </div>
                                <div class="ai-chat-form-group">
                                    <input type="email" id="${leadEmailId}" class="ai-chat-form-input" placeholder="Your Email *" required />
                                </div>
                                <div class="ai-chat-form-group">
                                    <input type="tel" id="${leadPhoneId}" class="ai-chat-form-input" placeholder="Your Phone Number" />
                                </div>
                                <div class="ai-chat-form-actions">
                                    <button type="button" id="${leadSubmitId}" class="ai-chat-lead-submit">Start Chatting</button>
                                    ${this.config.requireContactForGuests ? '' : `<button type="button" id="${leadSkipId}" class="ai-chat-lead-skip">Skip for now</button>`}
                                </div>
                            </div>
                        </div>

                        <!-- Input -->
                        <div class="ai-chat-input-container">
                            <textarea id="${inputId}" class="ai-chat-input" placeholder="Type your message..." rows="1"></textarea>
                            <button type="button" id="${sendId}" class="ai-chat-send-button">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0M4.5 7.5a.5.5 0 0 0 0 1h5.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5z"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Branding Footer - Minimal text only -->
                        ${this.config.brandingEnabled ? `
                        <div class="ai-chat-branding" style="padding:6px 12px; background:#ffffff; border-top:1px solid #f0f0f0; text-align:center; font-size:11px; color:#111;">
                            <a href="https://ai-chat.support" target="_blank" rel="noopener noreferrer" aria-label="Powered by ai chat" style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none; color:#111; font-family: 'Segoe UI', Helvetica, Arial, sans-serif;">
                                Powered by ai chat
                            </a>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', widgetHTML);
        }

        bindEvents() {
            const button = document.getElementById(this.ids.button);
            const closeBtn = document.getElementById(this.ids.close);
            const expandBtn = document.getElementById(this.ids.expand);
            const sendBtn = document.getElementById(this.ids.send);
            const input = document.getElementById(this.ids.input);
            const leadSubmit = document.getElementById(this.ids.leadSubmit);
            const leadSkip = document.getElementById(this.ids.leadSkip);

            if (!button) {
                console.error('AI Chat Widget: Button not found');
                return;
            }

            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggle();
            });
            
            if (closeBtn) {
                closeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggle();
                });
            }

            if (expandBtn) {
                expandBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleExpand();
                });
            }
            
            if (sendBtn) {
                sendBtn.addEventListener('click', () => this.sendMessage());
            }
            
            if (leadSubmit) {
                leadSubmit.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Lead submit button clicked');
                    this.submitLeadForm();
                });
            }
            
            if (leadSkip && !this.config.requireContactForGuests) {
                leadSkip.addEventListener('click', () => this.skipLeadForm());
            }
            
            if (input) {
                                   // Debug: Widget created
                                   console.log('[AI Widget] Widget created, forcing chat window hidden');
                                   const window = document.getElementById(this.ids.window);
                                   if (window) {
                                       window.style.display = 'none';
                                       window.style.visibility = 'hidden';
                                       console.log('[AI Widget] Chat window hidden on load:', window.style.display, window.style.visibility);
                                   }
                // Setup input auto-resize
                input.addEventListener('input', () => {
                    input.style.height = 'auto';
                    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
                });

                // Handle Enter key for sending message
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                    }
                });
            }

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.stopAgentPolling();
                } else if (this.isOpen) {
                    this.startAgentPolling();
                }
            });

            // Hide notification when opened
            button.addEventListener('click', () => {
                const notification = document.getElementById(this.ids.notification);
                if (notification) {
                    notification.style.display = 'none';
                }
            });
        }

        toggle() {
            const button = document.getElementById(this.ids.button);
            const window = document.getElementById(this.ids.window);
            
            this.isOpen = !this.isOpen;
            
            if (this.isOpen) {
                window.style.setProperty('display', 'flex', 'important');
                window.style.setProperty('visibility', 'visible', 'important');
                button.style.transform = 'scale(0.9)';
                
                // Track widget open
                this.trackAnalytics('widget_open');
                
                // Check if user is logged in by looking for auth indicators
                const isLoggedIn = document.querySelector('meta[name="user-authenticated"]') || 
                                  document.body.classList.contains('logged-in') ||
                                  window.Laravel && window.Laravel.user;
                
                // Show lead form if not captured yet AND user is not logged in
                if (!this.leadCaptured && !isLoggedIn) {
                    this.showLeadForm();
                } else {
                    if (this.config.requireContactForGuests && !this.hasValidRequiredContact(this.userInfo) && !isLoggedIn) {
                        this.leadCaptured = false;
                        this.showLeadForm();
                        this.startAgentPolling();
                        return;
                    }
                    this.leadCaptured = true; // Skip lead capture for logged in users
                    this.saveLeadCaptured(); // Persist this state
                    const input = document.getElementById(this.ids.input);
                    if (input) {
                        input.focus();
                    }
                    // Show welcome message if no messages yet
                    if (this.messages.length === 0) {
                        this.showWelcomeMessage();
                    }
                }

                this.startAgentPolling();
            } else {
                window.style.setProperty('display', 'none', 'important');
                window.style.setProperty('visibility', 'hidden', 'important');
                button.style.transform = 'scale(1)';
                this.stopAgentPolling();
            }
        }

        startAgentPolling() {
            if (this.agentPoller) return;
            this.fetchAgentMessages();
            this.agentPoller = setInterval(() => {
                if (this.isOpen && !document.hidden) {
                    this.fetchAgentMessages();
                }
            }, this.agentPollingIntervalMs);
        }

        stopAgentPolling() {
            if (this.agentPoller) {
                clearInterval(this.agentPoller);
                this.agentPoller = null;
            }
        }

        async fetchAgentMessages() {
            try {
                if (this.isPollingInFlight) return;
                this.isPollingInFlight = true;
                const url = `${this.config.apiUrl}/widget/${this.config.orgId}/messages?session_id=${encodeURIComponent(this.sessionId)}&last_id=${this.lastAgentMessageId}`;
                const response = await fetch(url, { method: 'GET' });
                if (!response.ok) return;
                const data = await response.json();
                const messages = Array.isArray(data.messages) ? data.messages : [];
                if (!messages.length) return;

                messages.forEach(msg => {
                    this.lastAgentMessageId = Math.max(this.lastAgentMessageId, msg.id || 0);
                    this.addMessage(msg.message, 'agent', msg.sender_name, msg.id);
                });
            } catch (e) {
                console.debug('[AI Widget] Agent polling failed:', e);
            } finally {
                this.isPollingInFlight = false;
            }
        }

        isContactQuery(text) {
            return /\b(contact|reach|email|phone|call|support|help|helpline|customer care|whatsapp|address|location)\b/i.test(text || '');
        }

        buildContactResponse() {
            const parts = [];
            if (this.config.contactEmail) parts.push(`Email: ${this.config.contactEmail}`);
            if (this.config.contactPhone) parts.push(`Phone: ${this.config.contactPhone}`);
            if (this.config.orgWebsite) parts.push(`Website: ${this.config.orgWebsite}`);
            return parts.length ? `You can reach us at ${parts.join(' | ')}.` : '';
        }

        toggleExpand() {
            const window = document.getElementById(this.ids.window);
            const expandBtn = document.getElementById(this.ids.expand);
            
            if (!window || !expandBtn) {
                console.error('AI Chat Widget: Window or expand button not found');
                return;
            }
            
            this.isExpanded = !this.isExpanded;
            
            if (this.isExpanded) {
                window.classList.add('ai-chat-expanded');
                expandBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 18 18">
                        <path d="M7 3H3v4h2V5h2V3zm4 0v2h2v2h2V3h-4zM7 15v-2H5v-2H3v4h4zm4 0h4v-4h-2v2h-2v2z" fill="currentColor"/>
                    </svg>
                `;
                expandBtn.title = 'Minimize chat';
            } else {
                window.classList.remove('ai-chat-expanded');
                expandBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 18 18">
                        <path d="M3 3h4v2H5v2H3V3zm8 0h4v4h-2V5h-2V3zM3 11v4h4v-2H5v-2H3zm10 0v2h-2v2h4v-4h-2z" fill="currentColor"/>
                    </svg>
                `;
                expandBtn.title = 'Expand chat';
            }
        }

        linkify(text) {
            if (!text) return '';
            
            // Preserve existing anchors by placeholdering them first
            const anchorPlaceholders = [];
            let anchorIndex = 0;
            text = text.replace(/<a\b[^>]*>.*?<\/a>/gi, (m) => {
                const ph = `__ANCHOR_${anchorIndex}__`;
                anchorPlaceholders.push(m);
                anchorIndex++;
                return ph;
            });

            // Strip any remaining HTML tags (defensive)
            text = text.replace(/<[^>]*>/g, '');
            
            // Normalize Markdown links: [label](url ... ) -> label (url)
            text = text.replace(/\[(.*?)\]\(([^)]+)\)/g, (m, label, inner) => {
                let url = '';
                const urlMatch = inner.match(/https?:\/\/[^\s)]+/i);
                if (urlMatch) url = urlMatch[0];
                else {
                    const dm = inner.match(/(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[^\s)]*)?/i);
                    if (dm) url = 'https://' + dm[0];
                }
                if (url) {
                    if (!label || label.toLowerCase() === url.toLowerCase()) return url;
                    return `${label} (${url})`;
                }
                return label || inner;
            });
            
            // Process links first, then escape remaining content
            let processed = text;
            
            // Store link placeholders to avoid escaping them
            const links = [];
            let linkIndex = 0;
            // Email placeholders to keep emails as plain text (no link)
            const emailPlaceholders = [];
            let emailIndex = 0;

            // Detect and temporarily replace email addresses with placeholders
            const emailRegex = /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/gi;
            processed = processed.replace(emailRegex, (em) => {
                const ph = `__EMAIL_${emailIndex}__`;
                emailPlaceholders[emailIndex] = em;
                emailIndex++;
                return ph;
            });
            
            // Detect URLs (http/https) and convert to anchors (with image handling)
            const urlRegex = /https?:\/\/[^\s<]+/g;
            processed = processed.replace(urlRegex, full => {
                const m = full.match(/^(.*?)([\.,!?)]?]?)$/);
                if (!m) return full;
                let url = m[1];
                let trail = full.substring(url.length);
                
                // If url ends with common punctuation, move it out
                while(/[\.,!?)]$/.test(url)) {
                    trail = url.slice(-1) + trail;
                    url = url.slice(0,-1);
                }
                // Image extensions
                const isImage = /\.(?:png|jpe?g|gif|webp|svg)(?:[?#].*)?$/i.test(url);
                const linkHtml = isImage
                    ? `<img src="${url}" alt="image" style="max-width:100%;height:auto;"/>${trail}`
                    : `<a href="${url}" target="_blank">${url}</a>${trail}`;
                const placeholder = `__LINK_${linkIndex}__`;
                links[linkIndex] = linkHtml;
                linkIndex++;
                return placeholder;
            });
            
            // Bare domains (e.g., example.com) -> prepend https
            // Use a safe regex that ensures the match is not part of an email by
            // requiring a non-@, non-word boundary before the domain (or start of string).
            const domainRegex = /(^|[^@\w])((?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,})(\/[\w\-._~:/?#[\]@!$&'()*+,;=%]*)?/g;
            processed = processed.replace(domainRegex, (full, pre, host, path = '') => {
                const url = `https://${host}${path || ''}`;
                const linkHtml = `${pre}<a href="${url}" target="_blank">${host}${path || ''}</a>`;
                const placeholder = `__LINK_${linkIndex}__`;
                links[linkIndex] = linkHtml;
                linkIndex++;
                return placeholder;
            });
            
            // Emails - do NOT convert to links; leave as plain text per requirement
            // (Intentionally disabled email linkification)
            
            // Now escape remaining content (but not link placeholders)
            const escapeMap = { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;' };
            processed = processed.replace(/[&<>"']/g, ch => escapeMap[ch] || ch);
            
            // Restore links
            for (let i = 0; i < links.length; i++) {
                processed = processed.replace(`__LINK_${i}__`, links[i]);
            }
            // Restore email placeholders as plain text (no link)
            for (let i = 0; i < emailPlaceholders.length; i++) {
                processed = processed.replace(`__EMAIL_${i}__`, emailPlaceholders[i]);
            }
            
            // Restore original anchors
            for (let i = 0; i < anchorPlaceholders.length; i++) {
                processed = processed.replace(`__ANCHOR_${i}__`, anchorPlaceholders[i]);
            }

            // Preserve line breaks
            return processed.replace(/\n/g, '<br>');
        }

        renderMessage(content, sender = 'user', timestamp = null, senderName = null) {
            const messagesContainer = document.getElementById(this.ids.messages);
            if (!messagesContainer) {
                console.error('AI Chat Widget: Messages container not found');
                return;
            }
            
            const messageElement = document.createElement('div');
            messageElement.className = `ai-chat-message ai-chat-message-${sender}`;
            
            const time = timestamp
                ? new Date(timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
                : new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            
                               console.log('[AI Widget] Closing chat window');
            const safeContent = sender === 'bot' ? this.linkify(content) : this.linkify(content); // both user & bot for consistency
            const senderLabel = sender === 'agent'
                ? `<div class="ai-chat-message-sender">${senderName || 'Support Agent'}</div>`
                : '';
            messageElement.innerHTML = `
                <div class="ai-chat-message-content">
                    ${senderLabel}
                    ${safeContent}
                </div>
                <div class="ai-chat-message-time">${time}</div>
            `;

            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        addMessage(content, sender = 'user', senderName = null, messageId = null) {
            this.renderMessage(content, sender, null, senderName);

            this.messages.push({ content, sender, senderName, messageId, timestamp: new Date() });
            // ISSUE 5B FIX: Persist messages after each addition
            this.saveMessages();
        }

        addStreamingMessage(fullContent) {
            const messagesContainer = document.getElementById(this.ids.messages);
            if (!messagesContainer) return;

            const messageElement = document.createElement('div');
            messageElement.className = 'ai-chat-message ai-chat-message-bot';
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            messageElement.innerHTML = `
                <div class="ai-chat-message-content"></div>
                <div class="ai-chat-message-time">${time}</div>
            `;
            const contentEl = messageElement.querySelector('.ai-chat-message-content');
            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // Type-out effect: append small chunks rapidly for perceived streaming
            const text = String(fullContent || '');
            let i = 0;
            const step = Math.max(2, Math.floor(text.length / 120)); // dynamic chunk size (~120 steps)
            const interval = 18; // ms per step

            const tick = () => {
                if (i >= text.length) {
                    // Final render with linkify for clickable URLs
                    contentEl.innerHTML = this.linkify(text);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    return;
                }
                const next = text.slice(0, i += step);
                // Light render without full linkify each tick for performance; apply simple escape
                const safe = next.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                contentEl.innerHTML = safe;
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                setTimeout(tick, interval);
            };
            tick();

            this.messages.push({ content: fullContent, sender: 'bot', timestamp: new Date() });
            // ISSUE 5B FIX: Persist messages after bot response
            this.saveMessages();
        }

        addTypingIndicator() {
            const messagesContainer = document.getElementById(this.ids.messages);
            if (!messagesContainer) return;
            
            const typingElement = document.createElement('div');
            typingElement.className = 'ai-chat-message ai-chat-message-bot ai-chat-typing';
            typingElement.id = 'ai-chat-typing-' + this.config.orgId;
            
            typingElement.innerHTML = `
                <div class="ai-chat-message-content">
                    <div class="ai-chat-typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            `;

            messagesContainer.appendChild(typingElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        removeTypingIndicator() {
            const typingElement = document.getElementById('ai-chat-typing-' + this.config.orgId);
            if (typingElement) {
                typingElement.remove();
            }
        }

        showLeadForm() {
            const leadForm = document.getElementById(this.ids.leadForm);
            const messagesContainer = document.getElementById(this.ids.messages);
            const widget = document.getElementById(this.ids.widget);
            const inputContainer = widget ? widget.querySelector('.ai-chat-input-container') : null;
            
            if (leadForm) leadForm.style.display = 'block';
            if (messagesContainer) messagesContainer.style.display = 'none';
            if (inputContainer) inputContainer.style.display = 'none';
            
            // Focus on name input
            const nameInput = document.getElementById(this.ids.leadName);
            if (nameInput) nameInput.focus();
        }

        hideLeadForm() {
               console.log('[AI Widget] showLeadForm called');
            console.log('hideLeadForm called');
            const leadForm = document.getElementById(this.ids.leadForm);
            const messagesContainer = document.getElementById(this.ids.messages);
            const widget = document.getElementById(this.ids.widget);
            const inputContainer = widget ? widget.querySelector('.ai-chat-input-container') : null;
            
            console.log('leadForm:', leadForm);
               console.log('[AI Widget] Lead form display:', leadForm ? leadForm.style.display : 'none');
            console.log('messagesContainer:', messagesContainer);
            console.log('widget:', widget);
            console.log('inputContainer:', inputContainer);
            
            if (leadForm) {
                leadForm.style.display = 'none';
                leadForm.style.setProperty('display', 'none', 'important');
                console.log('Lead form hidden');
            }
            if (messagesContainer) {
                messagesContainer.style.display = 'flex';
                messagesContainer.style.setProperty('display', 'flex', 'important');
                console.log('Messages container shown');
            }
            if (inputContainer) {
                inputContainer.style.display = 'flex';
                inputContainer.style.setProperty('display', 'flex', 'important');
                console.log('Input container shown');
            }
            
            // Show welcome message if no messages yet
            if (this.messages.length === 0) {
                this.showWelcomeMessage();
            }
            
            const input = document.getElementById(this.ids.input);
            if (input) input.focus();
        }

        async submitLeadForm() {
            console.log('submitLeadForm called');
            const name = document.getElementById(this.ids.leadName).value.trim();
            const email = document.getElementById(this.ids.leadEmail).value.trim();
            const phone = document.getElementById(this.ids.leadPhone).value.trim();

            if (!name) {
                alert('Please fill in your name.');
                return;
            }

            const emailRegex = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i;
            if (!email || !emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }

            const phoneRegex = /^\+?[0-9][0-9\-\s()]{6,19}$/;
            const phoneDigits = (phone.match(/\d/g) || []).length;
            const requireContact = !!this.config.requireContactForGuests;

            if (requireContact && !phone) {
                alert('Phone number is required to start chat.');
                return;
            }

            if (phone && (!phoneRegex.test(phone) || phoneDigits < 7 || phoneDigits > 15)) {
                alert('Please enter a valid phone number.');
                return;
            }

            this.userInfo = { name, email, phone };
            this.leadCaptured = true;
            this.saveLeadCaptured();
            this.saveUserInfo();

            await this.syncSessionFromStoredContact();
            
            // Store lead info (you can send this to server if needed)
            console.log('Lead captured:', this.userInfo);
            console.log('Calling hideLeadForm...');
            
            this.hideLeadForm();
            
            // Welcome message with name
            if (this.messages.length === 0) {
                this.showWelcomeMessage(`Hello ${name}! ${this.config.welcomeMessage}`);
            }
        }

        skipLeadForm() {
            if (this.config.requireContactForGuests) {
                return;
            }
            this.leadCaptured = true;
            this.saveLeadCaptured();
            this.hideLeadForm();
        }

        async detectLocation() {
            try {
                // Only use browser timezone info, no external API calls
                const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                this.locationInfo = { 
                    timezone,
                    // Extract basic region info from timezone if possible
                    region: timezone.includes('/') ? timezone.split('/')[0] : '',
                    city: timezone.includes('/') ? timezone.split('/').pop() : ''
                };
                console.log('Location detected (browser timezone):', this.locationInfo);
            } catch (error) {
                console.log('Could not detect location:', error);
                // Fallback to empty location info
                this.locationInfo = {};
            }
        }

        detectShopifyThemeColors() {
            // Only run if this is a Shopify store
            if (!this.config.isShopify) {
                return null;
            }

            try {
                // Try to detect primary colors from Shopify's common elements
                const selectors = [
                    'button[type="submit"]',
                    '.btn--primary',
                    '.product-form__submit',
                    '[class*="button--primary"]',
                    '[class*="btn-primary"]',
                    '.shopify-payment-button__button',
                    'button[name="add"]'
                ];

                for (const selector of selectors) {
                    const element = document.querySelector(selector);
                    if (element) {
                        const styles = window.getComputedStyle(element);
                        const bgColor = styles.backgroundColor;
                        
                        // Convert rgb to hex
                        if (bgColor && bgColor.startsWith('rgb')) {
                            const hex = this.rgbToHex(bgColor);
                            if (hex && hex !== '#000000' && hex !== '#ffffff') {
                                console.log('[AI Chat] Detected Shopify primary color:', hex);
                                return {
                                    primaryColor: hex
                                };
                            }
                        }
                    }
                }

                console.log('[AI Chat] Could not detect Shopify theme colors, using defaults');
                return null;
            } catch (error) {
                console.warn('[AI Chat] Error detecting Shopify colors:', error);
                return null;
            }
        }

        rgbToHex(rgb) {
            // Convert rgb(r, g, b) or rgba(r, g, b, a) to hex
            const match = rgb.match(/\d+/g);
            if (!match || match.length < 3) return null;
            
            const r = parseInt(match[0]);
            const g = parseInt(match[1]);
            const b = parseInt(match[2]);
            
            return '#' + [r, g, b].map(x => {
                const hex = x.toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            }).join('');
        }

        applyDynamicColors(colors) {
            if (!colors || !colors.primaryColor) return;

            // Apply colors to widget elements dynamically
            const style = document.createElement('style');
            style.id = 'ai-chat-dynamic-colors';
            style.innerHTML = `
                .ai-chat-button { background: ${colors.primaryColor} !important; }
                .ai-chat-header { background: ${colors.primaryColor} !important; }
                .ai-chat-message-user .ai-chat-message-content { background: ${colors.primaryColor} !important; }
                .ai-chat-send-button { background: ${colors.primaryColor} !important; }
                .ai-chat-lead-submit { background: ${colors.primaryColor} !important; }
                .ai-chat-input:focus { border-color: ${colors.primaryColor} !important; }
            `;
            document.head.appendChild(style);
        }

        trackAnalytics(eventType, data = {}) {
            // Track widget interactions in analytics
            try {
                const payload = {
                    org_id: this.config.orgId,
                    visitor_id: this.sessionId, // Use session ID as visitor ID for widgets
                    session_id: this.sessionId,
                    event_type: eventType,
                    page_url: window.location.href,
                    page_title: document.title,
                    event_data: {
                        widget_event: true,
                        ...data
                    }
                };

                // Send analytics data
                fetch('{{ config("app.url") }}/api/analytics/track', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                    keepalive: true
                }).catch(e => {
                    // Silent fail - analytics shouldn't break widget
                    console.debug('Analytics tracking failed:', e);
                });
            } catch (e) {
                // Silent fail
            }
        }

        isUnansweredResponse(text) {
            if (!text) return false;
            const t = text.toLowerCase();
            const patterns = [
                "i don't know",
                "i do not know",
                "not sure",
                "sorry, i don't",
                "sorry, i do not",
                "don't have that information",
                "do not have that information",
                "not available",
                "unable to",
                "can't find",
                "cannot find"
            ];
            return patterns.some(p => t.includes(p));
        }

        getWebSocketUrl() {
            return null;
        }

        initWebSocket() {
            const wsUrl = this.getWebSocketUrl();
            if (!wsUrl || !('WebSocket' in window)) {
                return;
            }
            this.openWebSocket();
        }

        openWebSocket() {
            const wsUrl = this.getWebSocketUrl();
            if (!wsUrl || this.wsReady || this.wsConnecting) {
                return;
            }

            this.wsConnecting = true;
            this.ws = new WebSocket(wsUrl);

            this.ws.onopen = () => {
                this.wsReady = true;
                this.wsConnecting = false;
                this.wsReconnectAttempts = 0;
                console.info('[AI Chat] WebSocket connected');

                if (this.wsPingTimer) {
                    clearInterval(this.wsPingTimer);
                }
                this.wsPingTimer = setInterval(() => {
                    if (this.wsReady && this.ws && this.ws.readyState === WebSocket.OPEN) {
                        try {
                            this.ws.send(JSON.stringify({ type: 'ping' }));
                        } catch (e) {
                            console.warn('[AI Chat] WebSocket ping failed:', e);
                        }
                    }
                }, 25000);
            };

            this.ws.onmessage = (event) => {
                this.handleWebSocketMessage(event);
            };

            this.ws.onerror = () => {
                if (this.wsStream && this.wsStream.finished) {
                    return;
                }
                this.wsReady = false;
                this.wsConnecting = false;
            };

            this.ws.onclose = () => {
                this.wsReady = false;
                this.wsConnecting = false;
                if (this.wsPingTimer) {
                    clearInterval(this.wsPingTimer);
                    this.wsPingTimer = null;
                }
                if (this.wsStream && !this.wsStream.finished) {
                    this.cleanupWebSocketStream(false);
                }
                if (this.wsShouldReconnect) {
                    const delay = Math.min(30000, 1000 * Math.pow(2, this.wsReconnectAttempts));
                    this.wsReconnectAttempts += 1;
                    setTimeout(() => this.openWebSocket(), delay);
                }
            };
        }

        handleWebSocketMessage(event) {
            let payload = null;
            try {
                payload = JSON.parse(event.data);
            } catch (e) {
                return;
            }

            if (payload && payload.type === 'pong') {
                return;
            }

            if (!this.wsStream) {
                return;
            }

            const stream = this.wsStream;
            if (payload && payload.error) {
                this.cleanupWebSocketStream(false, payload.error);
                return;
            }

            if (payload && payload.content) {
                if (!stream.botMessageElement) {
                    const messagesContainer = document.getElementById(this.ids.messages);
                    if (!messagesContainer) {
                        this.cleanupWebSocketStream(false);
                        return;
                    }

                    this.removeTypingIndicator();
                    stream.botMessageElement = document.createElement('div');
                    stream.botMessageElement.className = 'ai-chat-message ai-chat-message-bot';
                    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    stream.botMessageElement.innerHTML = `
                        <div class="ai-chat-message-content"></div>
                        <div class="ai-chat-message-time">${time}</div>
                    `;
                    messagesContainer.appendChild(stream.botMessageElement);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    stream.contentEl = stream.botMessageElement.querySelector('.ai-chat-message-content');
                    console.info('[AI Chat] WebSocket streaming started');
                }

                stream.hasContent = true;
                stream.fullResponse += payload.content;
                if (stream.contentEl) {
                    stream.contentEl.innerHTML = this.linkify(stream.fullResponse);
                }
            }

            if (payload && payload.done) {
                this.cleanupWebSocketStream(true);
            }
        }

        cleanupWebSocketStream(success, errorMessage = null) {
            if (!this.wsStream) {
                return;
            }

            const stream = this.wsStream;
            stream.finished = true;
            if (stream.firstChunkTimer) {
                clearTimeout(stream.firstChunkTimer);
            }

            if (!stream.hasContent) {
                if (stream.botMessageElement && stream.botMessageElement.parentNode) {
                    stream.botMessageElement.parentNode.removeChild(stream.botMessageElement);
                }
                this.removeTypingIndicator();
                const contactFallback = this.isContactQuery(stream.message) ? this.buildContactResponse() : '';
                if (contactFallback) {
                    this.addMessage(contactFallback, 'bot');
                } else {
                    this.addMessage(errorMessage ? 'Sorry, I encountered an error. Please try again.' : 'Sorry, I encountered an error. Please try again.', 'bot');
                }
                if (stream.resolve) {
                    stream.resolve(false);
                }
            } else {
                this.messages.push({ content: stream.fullResponse, sender: 'bot', timestamp: new Date() });
                this.saveMessages();
                if (stream.resolve) {
                    stream.resolve(true);
                }
            }

            this.wsBusy = false;
            this.wsStream = null;
        }

        async sendWebSocketStream(requestBody, message) {
            if (!this.wsReady || !this.ws || this.ws.readyState !== WebSocket.OPEN || this.wsBusy) {
                return false;
            }

            this.wsBusy = true;
            return await new Promise((resolve) => {
                this.wsStream = {
                    message,
                    fullResponse: '',
                    hasContent: false,
                    finished: false,
                    botMessageElement: null,
                    contentEl: null,
                    resolve,
                    firstChunkTimer: setTimeout(() => {
                        if (this.wsStream && !this.wsStream.hasContent && !this.wsStream.finished) {
                            this.cleanupWebSocketStream(false);
                        }
                    }, 2500)
                };

                try {
                    this.ws.send(JSON.stringify({
                        ...requestBody,
                        org_id: this.config.orgId
                    }));
                } catch (e) {
                    this.cleanupWebSocketStream(false);
                }
            });
        }

        async sendMessage() {
            if (!this.leadCaptured) {
                this.showLeadForm();
                return;
            }
            const input = document.getElementById(this.ids.input);
            if (!input) return;
            
            const message = input.value.trim();

            if (!message) return;

            // Track chat message
            this.trackAnalytics('chat_message', {
                message_length: message.length,
                has_user_info: !!this.userInfo.name
            });

            // Add user message
            this.addMessage(message, 'user');
            input.value = '';

            // Show typing indicator
            this.addTypingIndicator();

            try {
                const requestBody = {
                    message: message,
                    session_id: this.sessionId,
                    ...this.locationInfo
                };

                const urlParams = new URLSearchParams(window.location.search || '');
                requestBody.page_url = window.location.href;
                requestBody.page_title = document.title || null;
                requestBody.referrer = document.referrer || null;
                requestBody.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || null;
                requestBody.language = navigator.language || null;
                ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach((key) => {
                    const value = urlParams.get(key);
                    if (value) {
                        requestBody[key] = value;
                    }
                });

                // Include lead information if captured
                if (this.leadCaptured && this.userInfo.name) {
                    requestBody.visitor_info = this.userInfo;
                }
                
                // Add Shopify flag if this is a Shopify store
                if (this.config.isShopify) {
                    requestBody.is_shopify = true;
                }

                // Try WebSocket first (fallback to SSE)
                const wsSucceeded = await this.sendWebSocketStream(requestBody, message);
                if (wsSucceeded) {
                    return;
                }

                // Use fetch with streaming for real-time SSE responses
                const response = await fetch(`${this.config.apiUrl}/widget/${this.config.orgId}/chat/stream`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'text/event-stream'
                    },
                    body: JSON.stringify(requestBody)
                });

                // Handle rate limiting
                if (response.status === 429) {
                    const data = await response.json();
                    this.removeTypingIndicator();
                    const waitTime = data.retry_after || 60;
                    this.addMessage(`Please slow down! You can send up to 5 messages per minute. Please wait ${waitTime} seconds.`, 'bot');
                    return;
                }

                if (!response.ok) {
                    this.removeTypingIndicator();
                    let errorText = '';
                    try {
                        errorText = await response.text();
                    } catch (readErr) {
                        console.warn('[AI Chat] Failed to read error response:', readErr);
                    }
                    console.error('[AI Chat] Stream request failed:', {
                        status: response.status,
                        statusText: response.statusText,
                        body: errorText
                    });
                    const contactFallback = this.isContactQuery(message) ? this.buildContactResponse() : '';
                    if (contactFallback) {
                        this.addMessage(contactFallback, 'bot');
                    } else {
                        this.addMessage('Sorry, I encountered an error. Please try again.', 'bot');
                    }
                    return;
                }

                let botMessageElement = null;
                let contentEl = null;
                let firstTokenTimestampSet = false;
                let firstChunkReceived = false;

                // Read the stream
                let fullResponse = '';
                let hasContent = false;
                let buffer = '';

                if (!response.body) {
                    let fallbackText = '';
                    try {
                        fallbackText = await response.text();
                    } catch (readErr) {
                        console.warn('[AI Chat] Failed to read fallback response:', readErr);
                    }

                    console.warn('[AI Chat] Stream body missing, using fallback text:', fallbackText);

                    if (fallbackText.includes('data: ')) {
                        const parts = fallbackText.split('\n\n');
                        for (const part of parts) {
                            if (part.startsWith('data: ')) {
                                try {
                                    const data = JSON.parse(part.slice(6));
                                    if (data.content) {
                                        fullResponse += data.content;
                                    }
                                } catch (err) {
                                    console.error('Fallback parse error:', err);
                                }
                            }
                        }
                    } else if (fallbackText.trim().length > 0) {
                        fullResponse = fallbackText.trim();
                    }

                    this.removeTypingIndicator();

                    if (fullResponse.trim().length > 0) {
                        this.addMessage(fullResponse, 'bot');
                    } else {
                        const contactFallback = this.isContactQuery(message) ? this.buildContactResponse() : '';
                        if (contactFallback) {
                            this.addMessage(contactFallback, 'bot');
                        } else {
                            this.addMessage('Sorry, I encountered an error. Please try again.', 'bot');
                        }
                    }
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    // Decode chunk and add to buffer
                    buffer += decoder.decode(value, { stream: true });
                    
                    // Process complete SSE messages
                    const lines = buffer.split('\n\n');
                    buffer = lines.pop(); // Keep incomplete message in buffer

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            try {
                                const data = JSON.parse(line.slice(6));
                                
                                if (data.error) {
                                    if (!hasContent) {
                                        if (!firstChunkReceived) {
                                            this.removeTypingIndicator();
                                        }
                                        const contactFallback = this.isContactQuery(message) ? this.buildContactResponse() : '';
                                        if (contactFallback) {
                                            this.addMessage(contactFallback, 'bot');
                                        } else {
                                            this.addMessage('Sorry, I encountered an error. Please try again.', 'bot');
                                        }
                                    } else {
                                        console.warn('[AI Chat] Stream error after partial response:', data.error);
                                    }
                                    return;
                                }
                                
                                // Append content as it streams
                                if (data.content) {
                                    if (!firstChunkReceived) {
                                        firstChunkReceived = true;
                                        this.removeTypingIndicator();

                                        const messagesContainer = document.getElementById(this.ids.messages);
                                        botMessageElement = document.createElement('div');
                                        botMessageElement.className = 'ai-chat-message ai-chat-message-bot';
                                        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                                        botMessageElement.innerHTML = `
                                            <div class="ai-chat-message-content"></div>
                                            <div class="ai-chat-message-time">${time}</div>
                                        `;
                                        messagesContainer.appendChild(botMessageElement);
                                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                        contentEl = botMessageElement.querySelector('.ai-chat-message-content');
                                    }

                                    if (!firstTokenTimestampSet && botMessageElement) {
                                        const firstTokenTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                                        const timeEl = botMessageElement.querySelector('.ai-chat-message-time');
                                        if (timeEl) {
                                            timeEl.textContent = firstTokenTime;
                                        }
                                        firstTokenTimestampSet = true;
                                    }
                                    fullResponse += data.content;
                                    hasContent = true;
                                    if (contentEl) {
                                        contentEl.innerHTML = this.linkify(fullResponse);
                                    }
                                    if (botMessageElement && botMessageElement.parentNode) {
                                        botMessageElement.parentNode.scrollTop = botMessageElement.parentNode.scrollHeight;
                                    }
                                }
                            } catch (err) {
                                console.error('Stream parse error:', err, { line });
                            }
                        }
                    }
                }

                if (!hasContent) {
                    this.removeTypingIndicator();
                    const contactFallback = this.isContactQuery(message) ? this.buildContactResponse() : '';
                    if (contactFallback) {
                        this.addMessage(contactFallback, 'bot');
                    } else {
                        this.addMessage('Sorry, I encountered an error. Please try again.', 'bot');
                    }
                    return;
                }

                if (fullResponse.trim().length > 0) {
                    this.messages.push({ content: fullResponse, sender: 'bot', timestamp: new Date() });
                    this.saveMessages();
                    if (this.isUnansweredResponse(fullResponse)) {
                        this.trackAnalytics('unanswered_question', {
                            message,
                            response: fullResponse
                        });
                    }
                }

            } catch (error) {
                console.error('Chat error:', {
                    name: error?.name,
                    message: error?.message,
                    stack: error?.stack
                });
                this.removeTypingIndicator();
                if (!hasContent) {
                    const contactFallback = this.isContactQuery(message) ? this.buildContactResponse() : '';
                    if (contactFallback) {
                        this.addMessage(contactFallback, 'bot');
                    } else {
                        this.addMessage('Sorry, I\'m experiencing technical difficulties. Please try again later.', 'bot');
                    }
                }
            }
        }
    }

    // Initialize widget when DOM is ready
    const start = () => {
        if (window.__AiChatWidgetInstances[__ORG_KEY__]) return; // prevent re-init per org
        const instance = new AiChatWidget(config);
        window.__AiChatWidgetInstances[__ORG_KEY__] = instance;
        // Backwards compatibility pointer
        window.AiChatWidget = instance;
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

})();
