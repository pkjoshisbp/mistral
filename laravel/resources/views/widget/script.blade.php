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
            this.contactFields = this.normalizeContactFields(this.config.contactFields);
            this.starterPrompts = this.normalizeStarterPrompts(this.config.starterPrompts || []);
            this.starterPromptsShown = false;
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
                this.showStarterPrompts();
            }, 500);
        }

        normalizeStarterPrompts(prompts) {
            if (!Array.isArray(prompts)) {
                return [];
            }

            const cleaned = [];
            for (const prompt of prompts) {
                const value = String(prompt || '').trim();
                if (!value) {
                    continue;
                }
                if (cleaned.includes(value)) {
                    continue;
                }
                cleaned.push(value);
                if (cleaned.length >= 6) {
                    break;
                }
            }

            return cleaned;
        }

        showStarterPrompts() {
            if (this.starterPromptsShown || !Array.isArray(this.starterPrompts) || this.starterPrompts.length === 0) {
                return;
            }

            const messagesContainer = document.getElementById(this.ids.messages);
            if (!messagesContainer) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'ai-chat-starter-prompts';
            wrapper.id = 'ai-chat-starter-prompts-' + this.config.orgId;

            const title = document.createElement('div');
            title.className = 'ai-chat-starter-title';
            title.textContent = 'Quick questions';
            wrapper.appendChild(title);

            const chips = document.createElement('div');
            chips.className = 'ai-chat-starter-list';

            this.starterPrompts.forEach((promptText) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ai-chat-starter-chip';
                btn.textContent = promptText;
                btn.addEventListener('click', () => this.sendMessage(promptText));
                chips.appendChild(btn);
            });

            wrapper.appendChild(chips);
            messagesContainer.appendChild(wrapper);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            this.starterPromptsShown = true;
        }

        removeStarterPrompts() {
            const node = document.getElementById('ai-chat-starter-prompts-' + this.config.orgId);
            if (node) {
                node.remove();
            }
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

        normalizeContactFields(fields) {
            if (!Array.isArray(fields)) {
                return [];
            }

            const allowedTypes = ['text', 'email', 'phone', 'number', 'location'];
            const seen = new Set();

            return fields
                .map((field) => {
                    const keyRaw = String(field?.key || '').trim().toLowerCase();
                    const key = keyRaw.replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
                    if (!key || seen.has(key)) {
                        return null;
                    }
                    seen.add(key);

                    const type = allowedTypes.includes(String(field?.type || '').toLowerCase())
                        ? String(field.type).toLowerCase()
                        : 'text';
                    const label = String(field?.label || key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()));
                    const required = !!field?.required;
                    const placeholder = String(field?.placeholder || `Your ${label}${required ? ' *' : ''}`);

                    return { key, type, label, required, placeholder };
                })
                .filter(Boolean);
        }

        escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
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
            this.applyCustomWidgetJs();

            // Track page view when widget script loads (delayed to avoid blocking render)
            setTimeout(() => {
                this.trackAnalytics('page_view', {
                    referrer: document.referrer || null,
                    user_agent: navigator.userAgent,
                });
            }, 1500);

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
            const version = encodeURIComponent(String(this.config.scriptVersion || Date.now()));
            link.href = `${this.config.apiUrl}/widget/${this.config.orgId}/styles.css?v=${version}`;
            document.head.appendChild(link);
        }

        getViewportHeight() {
            const visualHeight = window.visualViewport && Number(window.visualViewport.height)
                ? Number(window.visualViewport.height)
                : 0;
            const windowHeight = Number(window.innerHeight) || 0;
            const docHeight = Number(document.documentElement?.clientHeight) || 0;
            return Math.max(visualHeight, windowHeight, docHeight, 0);
        }

        applyViewportBounds() {
            const chatWindow = document.getElementById(this.ids?.window);
            if (!chatWindow) {
                return;
            }

            // On mobile when open, CSS handles fullscreen — no JS overrides needed
            if (this.isMobile() && this.isOpen) return;

            const offsetY = Number(this.config?.offsetY || 20);
            const launcherHeight = 60;
            const breathingSpace = 12;
            const viewportHeight = this.getViewportHeight();
            const availableHeight = Math.max(320, viewportHeight - (launcherHeight + offsetY + breathingSpace));

            chatWindow.style.setProperty('max-height', `${Math.floor(availableHeight)}px`, 'important');

            if (this.isExpanded) {
                const targetExpandedHeight = Math.min(900, availableHeight);
                chatWindow.style.setProperty('height', `${Math.floor(targetExpandedHeight)}px`, 'important');
            } else {
                chatWindow.style.removeProperty('height');
            }
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
            const leadCustomFieldIds = {};
            this.contactFields.forEach((field) => {
                leadCustomFieldIds[field.key] = 'ai-lead-field-' + field.key + '-' + this.config.orgId;
            });
            
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
                leadCustomFields: leadCustomFieldIds,
                leadSubmit: leadSubmitId,
                leadSkip: leadSkipId
            };

            const customLeadFieldsHtml = this.contactFields.map((field) => {
                const fieldId = this.ids.leadCustomFields[field.key];
                let inputType = 'text';
                if (field.type === 'email') inputType = 'email';
                if (field.type === 'number') inputType = 'number';
                if (field.type === 'phone') inputType = 'tel';
                if (field.type === 'location') inputType = 'text';

                return `
                    <div class="ai-chat-form-group">
                        <input type="${inputType}" id="${fieldId}" class="ai-chat-form-input" placeholder="${this.escapeHtml(field.placeholder)}" ${field.required ? 'required' : ''} />
                    </div>
                `;
            }).join('');

            // Create widget container
            const widgetHTML = `
                <div id="${widgetId}" class="ai-chat-widget ${this.config.position}" style="--ai-offset-x: ${this.config.offsetX || 20}px; --ai-offset-y: ${this.config.offsetY || 20}px;">
                    <!-- Chat Button -->
                    <div id="${buttonId}" class="ai-chat-button">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color:${this.config.widgetIconColor || '#ffffff'};fill:${this.config.widgetIconColor || '#ffffff'};">
                            <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z" fill="${this.config.widgetIconColor || '#ffffff'}"/>
                            <circle cx="8" cy="10" r="1" fill="${this.config.widgetIconColor || '#ffffff'}"/>
                            <circle cx="12" cy="10" r="1" fill="${this.config.widgetIconColor || '#ffffff'}"/>
                            <circle cx="16" cy="10" r="1" fill="${this.config.widgetIconColor || '#ffffff'}"/>
                        </svg>
                        <span class="ai-chat-notification" id="${notificationId}">1</span>
                    </div>

                    ${this.config.brandingEnabled && this.config.brandingBadge && !this.config.standardAttribution ? `
                        <div class="ai-chat-badge" style="position:absolute; ${this.config.position.includes('bottom') ? 'bottom: -20px;' : 'top: -20px;'} ${this.config.position.includes('right') ? 'right: 0;' : 'left: 0;'} opacity: 0.7;">
                            <a href="https://ai-chat.support" target="_blank" rel="nofollow noopener noreferrer" aria-label="Powered by ai chat" style="display:inline-flex;align-items:center;text-decoration:none; font-size:11px; color:#111; font-family: 'Segoe UI', Helvetica, Arial, sans-serif;">
                                Powered by ai chat
                            </a>
                        </div>
                    ` : ''}

                    <!-- Chat Window -->
                    <div id="${windowId}" class="ai-chat-window" style="display: none;">
                        <!-- Header -->
                        <div class="ai-chat-header" style="display:flex!important;align-items:center!important;flex-wrap:nowrap!important;width:100%!important;margin:0!important;padding:calc(18px + env(safe-area-inset-top)) 22px 18px 22px!important;gap:14px!important;box-sizing:border-box!important;">
                            ${this.config.showHeaderLogo && this.config.headerLogoUrl ? `
                                <div class="ai-chat-logo" style="margin:0!important;padding:0!important;flex-shrink:0!important;">
                                    <img src="${this.config.headerLogoUrl}" alt="${this.config.orgName} logo" onerror="this.style.display='none'" />
                                </div>
                            ` : ''}
                            <div class="ai-chat-header-info" style="display:flex!important;flex-direction:column!important;flex:1 1 auto!important;min-width:0!important;margin:0!important;padding:0!important;overflow:hidden!important;">
                                <div class="ai-chat-title" style="margin:0 0 4px 0!important;padding:0!important;">${this.config.orgName}</div>
                                <div class="ai-chat-status" style="display:flex!important;align-items:center!important;gap:6px!important;margin:0!important;padding:0!important;">
                                    <span class="ai-chat-status-dot" style="margin:0!important;padding:0!important;flex-shrink:0!important;"></span>
                                    Online
                                </div>
                            </div>
                            <div class="ai-chat-header-actions" style="display:flex!important;align-items:center!important;gap:8px!important;flex:0 0 auto!important;margin:0!important;padding:0!important;">
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
                        <div id="${messagesId}" class="ai-chat-messages" style="display:flex!important;flex-direction:column!important;width:100%!important;margin:0!important;padding:16px!important;box-sizing:border-box!important;align-items:flex-start!important;">
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
                                ${customLeadFieldsHtml}
                                <div class="ai-chat-form-actions">
                                    <button type="button" id="${leadSubmitId}" class="ai-chat-lead-submit">Start Chatting</button>
                                    ${this.config.requireContactForGuests ? '' : `<button type="button" id="${leadSkipId}" class="ai-chat-lead-skip">Skip for now</button>`}
                                </div>
                            </div>
                        </div>

                        <!-- Input -->
                        <div class="ai-chat-input-container" style="display:flex!important;align-items:center!important;width:100%!important;margin:0!important;padding:12px 16px!important;box-sizing:border-box!important;gap:8px!important;">
                            <textarea id="${inputId}" class="ai-chat-input" placeholder="Type your message..." rows="1" style="margin:0!important;"></textarea>
                            <button type="button" id="${sendId}" class="ai-chat-send-button">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0M4.5 7.5a.5.5 0 0 0 0 1h5.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5z"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Branding Footer / Shopify Standard Attribution -->
                        ${this.config.brandingEnabled ? `
                        <div class="ai-chat-branding" style="padding:6px 12px; background:#ffffff; border-top:1px solid #f0f0f0; text-align:center; font-size:11px; color:#111;">
                            ${this.config.standardAttribution ? `
                                <a href="https://ai-chat.support" target="_blank" rel="noopener noreferrer nofollow" class="ai-chat-attribution-link" aria-label="App attribution" title="Powered by AI Chat Support" style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;line-height:1;text-decoration:none;overflow:hidden;border-radius:50%;background:#000000;box-shadow:0 1px 3px rgba(0,0,0,.24);font-family:Inter,'Segoe UI',Arial,sans-serif;font-size:9px;font-weight:700;color:#ffffff;letter-spacing:.2px;">
                                    AI
                                </a>
                            ` : `
                                <a href="https://ai-chat.support" target="_blank" rel="noopener noreferrer" aria-label="Powered by ai chat" style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none; color:#111; font-family: 'Segoe UI', Helvetica, Arial, sans-serif;">
                                    Powered by ai chat
                                </a>
                            `}
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', widgetHTML);
            this.applyHostCSSResetFixes();
        }

        /**
         * Apply inline styles to all critical widget elements to counter hostile
         * host-site CSS resets (e.g. reset.css: margin:auto + background:transparent on all divs).
         * Inline styles set via setProperty(...,'important') beat ALL external stylesheets.
         */
        applyHostCSSResetFixes() {
            const widget = document.getElementById(this.ids.widget);
            if (!widget) return;

            // Helper: apply map of property->value with !important
            const fix = (selector, props) => {
                const el = widget.querySelector(selector);
                if (!el) return;
                for (const [prop, val] of Object.entries(props)) {
                    el.style.setProperty(prop, val, 'important');
                }
            };

            // Header wrapper
            fix('.ai-chat-header', {
                'display': 'flex',
                'align-items': 'center',
                'flex-wrap': 'nowrap',
                'width': '100%',
                'margin': '0',
                'padding': 'calc(18px + env(safe-area-inset-top)) 22px 18px 22px',
                'gap': '14px',
                'box-sizing': 'border-box',
            });

            // Header info (title + status column)
            fix('.ai-chat-header-info', {
                'display': 'flex',
                'flex-direction': 'column',
                'flex': '1 1 auto',
                'min-width': '0',
                'margin': '0',
                'padding': '0',
                'overflow': 'hidden',
            });

            // Title
            fix('.ai-chat-title', {
                'margin': '0 0 4px 0',
                'padding': '0',
                'display': 'block',
            });

            // Status row (dot + "Online")
            fix('.ai-chat-status', {
                'display': 'flex',
                'align-items': 'center',
                'gap': '6px',
                'margin': '0',
                'padding': '0',
            });

            // Header actions (expand/close buttons)
            fix('.ai-chat-header-actions', {
                'display': 'flex',
                'align-items': 'center',
                'gap': '8px',
                'flex': '0 0 auto',
                'margin': '0',
                'padding': '0',
            });

            // Messages container
            const msgContainer = document.getElementById(this.ids.messages);
            if (msgContainer) {
                msgContainer.style.setProperty('width', '100%', 'important');
                msgContainer.style.setProperty('align-items', 'flex-start', 'important');
                msgContainer.style.setProperty('margin', '0', 'important');
                msgContainer.style.setProperty('padding', '22px 22px 12px 22px', 'important');
            }

            // Input container
            fix('.ai-chat-input-container', {
                'display': 'flex',
                'align-items': 'center',
                'width': '100%',
                'margin': '0',
                'padding': '18px 20px 20px 20px',
                'flex': '0 0 auto',
                'box-sizing': 'border-box',
            });
        }

        bindEvents() {
            const button = document.getElementById(this.ids.button);
            const closeBtn = document.getElementById(this.ids.close);
            const expandBtn = document.getElementById(this.ids.expand);
            const sendBtn = document.getElementById(this.ids.send);
            const input = document.getElementById(this.ids.input);
            const leadSubmit = document.getElementById(this.ids.leadSubmit);
            const leadSkip = document.getElementById(this.ids.leadSkip);
            const widget = document.getElementById(this.ids.widget);

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
                                   const chatWindow = document.getElementById(this.ids.window);
                                   if (chatWindow) {
                                       chatWindow.style.setProperty('display', 'none', 'important');
                                       chatWindow.style.setProperty('visibility', 'hidden', 'important');
                                       console.log('[AI Widget] Chat window hidden on load:', chatWindow.style.display, chatWindow.style.visibility);
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

            window.addEventListener('resize', () => this.applyViewportBounds(), { passive: true });
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', () => this.applyViewportBounds(), { passive: true });
                window.visualViewport.addEventListener('scroll', () => this.applyViewportBounds(), { passive: true });
            }

            // Hide notification when opened
            button.addEventListener('click', () => {
                const notification = document.getElementById(this.ids.notification);
                if (notification) {
                    notification.style.display = 'none';
                }
            });

            // Delegated click on images in chat messages — open full image in new tab
            const _msgEl = document.getElementById(this.ids.messages);
            if (_msgEl) {
                _msgEl.addEventListener('click', (e) => {
                    const img = e.target.closest('.ai-chat-message-content img');
                    if (img && img.src) {
                        e.preventDefault();
                        window.open(img.src, '_blank', 'noopener,noreferrer');
                    }
                });
            }
        }

        toggle() {
            const button = document.getElementById(this.ids.button);
            const chatWindow = document.getElementById(this.ids.window);
            const widgetEl = document.getElementById(this.ids.widget);
            
            this.isOpen = !this.isOpen;
            
            if (this.isOpen) {
                chatWindow.style.setProperty('display', 'flex', 'important');
                chatWindow.style.setProperty('visibility', 'visible', 'important');
                if (this.isMobile()) {
                    widgetEl?.classList.add('ai-mobile-open');
                } else {
                    this.applyViewportBounds();
                    button.style.transform = 'scale(0.9)';
                }
                
                // Track widget open
                this.trackAnalytics('widget_open');
                
                // Check if user is logged in by looking for auth indicators
                const isLoggedIn = document.querySelector('meta[name="user-authenticated"]') || 
                                  document.body.classList.contains('logged-in') ||
                                  (globalThis.window && globalThis.window.Laravel && globalThis.window.Laravel.user);
                
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
                    if (input && !this.isMobile()) {
                        input.focus();
                    }
                    // Show welcome message if no messages yet
                    if (this.messages.length === 0) {
                        this.showWelcomeMessage();
                    }
                }

                this.startAgentPolling();
            } else {
                chatWindow.style.setProperty('display', 'none', 'important');
                chatWindow.style.setProperty('visibility', 'hidden', 'important');
                widgetEl?.classList.remove('ai-mobile-open');
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

        isMobile() {
            return window.innerWidth <= 768;
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
            const chatWindow = document.getElementById(this.ids.window);
            const expandBtn = document.getElementById(this.ids.expand);
            
            if (!chatWindow || !expandBtn) {
                console.error('AI Chat Widget: Window or expand button not found');
                return;
            }
            
            this.isExpanded = !this.isExpanded;
            
            if (this.isExpanded) {
                chatWindow.classList.add('ai-chat-expanded');
                expandBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 18 18">
                        <path d="M7 3H3v4h2V5h2V3zm4 0v2h2v2h2V3h-4zM7 15v-2H5v-2H3v4h4zm4 0h4v-4h-2v2h-2v2z" fill="currentColor"/>
                    </svg>
                `;
                expandBtn.title = 'Minimize chat';
            } else {
                chatWindow.classList.remove('ai-chat-expanded');
                expandBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 18 18">
                        <path d="M3 3h4v2H5v2H3V3zm8 0h4v4h-2V5h-2V3zM3 11v4h4v-2H5v-2H3zm10 0v2h-2v2h4v-4h-2z" fill="currentColor"/>
                    </svg>
                `;
                expandBtn.title = 'Expand chat';
            }

            this.applyViewportBounds();
        }

        linkify(text) {
            if (!text) return '';

            text = this.normalizeShopifyFieldLayout(String(text));

            // Strip XML/HTML processing instructions (xml-pi tags, <!DOCTYPE ...>)
            text = text.replace(/<\?[^>]*>/g, '').replace(/<!DOCTYPE[^>]*>/gi, '');

            // ── Numbered list normalisation (text-only, safe before HTML processing) ──
            // Case 1: LLM puts the number at the end of the previous line:
            //   "details: 1.\n**Item**"  →  "details:\n\n1. **Item**"
            // Exclude "/" and digits as preceding char to avoid splitting "24/7.\n" → "24/\n\n7."
            text = text.replace(/([^\n\d/])\s*(\d+)\.\s*\n/g, '$1\n\n$2. ');
            // Case 2: number already starts a line but no blank line before it
            text = text.replace(/\n(\d+\.\s)/g, '\n\n$1');
            // Case 3: fully inline list — LLM emits no newlines at all:
            //   "...buy. 2. Basic: ..."  or  "plans: 1. Free: ..."
            // Insert a double newline before each "N. " that follows sentence/clause punctuation.
            // Guard: lookahead ensures the char after "N. " is not another digit (avoids
            // splitting "version 1. 2 features" type text).
            text = text.replace(/([.!?:,)])\s+(\d+\.\s+)(?=\D)/g, '$1\n\n$2');

            // ── Bold/italic placeholders (applied after HTML-escape below) ──────────
            // We use placeholders so the defensive tag-strip doesn't eat them.
            const boldPH = [], italicPH = [];
            text = text.replace(/\*\*([^*\n]+?)\*\*/g, (_, inner) => {
                boldPH.push(inner); return `__BOLD_${boldPH.length - 1}__`;
            });
            text = text.replace(/\*([^*\n]+?)\*/g, (_, inner) => {
                italicPH.push(inner); return `__ITALIC_${italicPH.length - 1}__`;
            });

            // Preserve existing anchors by placeholdering them first
            const anchorPlaceholders = [];
            let anchorIndex = 0;
            text = text.replace(/<a\b[^>]*>.*?<\/a>/gi, (m) => {
                const ph = `__ANCHOR_${anchorIndex}__`;
                anchorPlaceholders.push(m);
                anchorIndex++;
                return ph;
            });

            // ── Preserve safe HTML tags so FAQ/answer HTML renders properly ──────
            // Tags are sanitized (event handlers, javascript: stripped) to prevent XSS.
            const safeTags = 'p|ul|ol|li|strong|em|b|i|br|h[1-6]|blockquote|code|pre|hr|img';
            const safeTagRx = new RegExp(`</?(?:${safeTags})(?:\\s[^>]*)?>`, 'gi');
            const safeTagPlaceholders = [];
            let safeTagIdx = 0;
            text = text.replace(safeTagRx, (m) => {
                let safe = m.replace(/\s+on\w+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]*)/gi, '');
                safe = safe.replace(/javascript\s*:/gi, '');
                // For <img>: sanitize src — allow only data:image/* and http/https
                if (/^<img\b/i.test(safe)) {
                    safe = safe.replace(/\bsrc\s*=\s*"([^"]*)"/gi, (orig, v) =>
                        /^(data:image\/|https?:\/\/)/i.test(v) ? orig : '');
                    safe = safe.replace(/\bsrc\s*=\s*'([^']*)'/gi, (orig, v) =>
                        /^(data:image\/|https?:\/\/)/i.test(v) ? orig : '');
                }
                const ph = `__SAFETAG_${safeTagIdx}__`;
                safeTagPlaceholders.push(safe);
                safeTagIdx++;
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

                // Build a short display label for long URLs so they don't overflow the bubble
                let displayUrl = url;
                try {
                    const parsed = new URL(url);
                    // Show host + truncated path (max 40 chars total)
                    const full2 = parsed.hostname + parsed.pathname + parsed.search;
                    displayUrl = full2.length > 48 ? full2.slice(0, 45) + '…' : full2;
                } catch(e) { /* leave as-is */ }

                const linkHtml = isImage
                    ? `<img src="${url}" alt="image" style="max-width:100%;height:auto;"/>${trail}`
                    : `<a href="${url}" target="_blank" rel="noopener noreferrer" title="${url}">${displayUrl}</a>${trail}`;
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

            // Restore bold/italic placeholders as HTML (inner text was not yet escaped,
            // so escape it now before wrapping in tags)
            const _esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            for (let i = 0; i < boldPH.length; i++) {
                processed = processed.replace(`__BOLD_${i}__`, `<strong>${_esc(boldPH[i])}</strong>`);
            }
            for (let i = 0; i < italicPH.length; i++) {
                processed = processed.replace(`__ITALIC_${i}__`, `<em>${_esc(italicPH[i])}</em>`);
            }

            // Restore safe HTML tags (after escaping so placeholders remain untouched)
            for (let i = 0; i < safeTagPlaceholders.length; i++) {
                processed = processed.replace(`__SAFETAG_${i}__`, safeTagPlaceholders[i]);
            }

            // Preserve line breaks — when block HTML is present, strip formatting
            // whitespace between tags to prevent spurious <br> inside <ul>/<li> etc.
            const hasBlockHtml = safeTagPlaceholders.some(t => /^<(?:p|ul|ol|li|h[1-6]|blockquote|pre)/i.test(t));
            if (hasBlockHtml) {
                processed = processed.replace(/>\n+/g, '>').replace(/\n+</g, '<').replace(/\n/g, '<br>');
                return processed;
            }

            // ── Markdown bullet list grouping ─────────────────────────────────────
            // Groups consecutive "- text", "• text", or "* text" (not "**bold**") lines
            // into <ul><li>…</li></ul> blocks. Runs after all placeholder restorations.
            processed = (function(t) {
                const ls = t.split('\n');
                const out = [];
                let items = [];
                const flush = () => {
                    if (!items.length) return;
                    out.push('<ul>' + items.map(x => '<li>' + x + '</li>').join('') + '</ul>');
                    items = [];
                };
                for (const line of ls) {
                    const m = line.match(/^[ \t]*(?:[-•]|\*(?!\S))[ \t]+(.+)$/);
                    if (m) { items.push(m[1]); }
                    else   { flush(); out.push(line); }
                }
                flush();
                return out.join('\n');
            })(processed);
            if (processed.includes('<ul>')) {
                processed = processed.replace(/>\n+/g, '>').replace(/\n+</g, '<').replace(/\n/g, '<br>');
                return processed;
            }
            return processed.replace(/\n/g, '<br>');
        }

        normalizeShopifyFieldLayout(text) {
            if (!text) {
                return '';
            }

            const looksLikeShopifyStatus = /(tracking number|tracking link|carrier|status|shipped on|fulfilled on|delivered on|estimated delivery|order number)/i.test(text);
            if (!looksLikeShopifyStatus) {
                return text;
            }

            let normalized = String(text).replace(/\r\n?/g, '\n');

            // Fix malformed markdown labels produced by some Shopify responses, e.g.
            // "**\nTracking Number:** 123" or stray lines containing only "**".
            normalized = normalized.replace(/^\s*\*\*\s*$/gm, '');
            normalized = normalized.replace(/\*\*\s*\n+\s*((?:Status|Tracking Number|Tracking Link|Carrier|Shipped On|Fulfilled On|Delivered On|Estimated Delivery|Order Number)\s*:\*\*)/gi, '**$1');
            normalized = normalized.replace(/(^|\n)\s*((?:Status|Tracking Number|Tracking Link|Carrier|Shipped On|Fulfilled On|Delivered On|Estimated Delivery|Order Number)\s*:)\s*\*\*/gi, '$1**$2**');
            normalized = normalized.replace(/\n{2,}\*\*\s*(?=\*\*[A-Z])/g, '\n');

            // Force each common Shopify field label onto its own line.
            normalized = normalized.replace(
                /([^\n])\s*(\*\*(?:Status|Tracking Number|Tracking Link|Carrier|Shipped On|Fulfilled On|Delivered On|Estimated Delivery|Order Number)\s*:\*\*)/gi,
                '$1\n$2'
            );
            normalized = normalized.replace(
                /([^\n])\s*((?:Status|Tracking Number|Tracking Link|Carrier|Shipped On|Fulfilled On|Delivered On|Estimated Delivery|Order Number)\s*:)/gi,
                '$1\n$2'
            );

            // Keep common trailing values from being glued to the previous field.
            normalized = normalized.replace(/([^\n])\s+(UPS|FedEx|USPS|DHL)\b/g, '$1\n$2');

            return normalized.replace(/\n{3,}/g, '\n\n').trim();
        }

        applyMessageInlineStyles(el, sender) {
            // Inline styles beat ALL external CSS including hostile reset.css (margin:auto on div)
            el.style.setProperty('margin', '0', 'important');
            el.style.setProperty('padding', '0', 'important');
            el.style.setProperty('border', '0', 'important');
            el.style.setProperty('outline', '0', 'important');
            el.style.setProperty('background', 'transparent', 'important');
            el.style.setProperty('display', 'flex', 'important');
            el.style.setProperty('flex-direction', 'column', 'important');
            el.style.setProperty('max-width', '82%', 'important');
            el.style.setProperty('width', 'auto', 'important');
            if (sender === 'user') {
                el.style.setProperty('align-self', 'flex-end', 'important');
                el.style.setProperty('margin-left', 'auto', 'important');
                el.style.setProperty('margin-right', '0', 'important');
            } else {
                el.style.setProperty('align-self', 'flex-start', 'important');
                el.style.setProperty('margin-left', '0', 'important');
                el.style.setProperty('margin-right', 'auto', 'important');
            }

            const contentEl = el.querySelector('.ai-chat-message-content');
            if (contentEl) {
                contentEl.style.setProperty('font-family', "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif", 'important');
                contentEl.style.setProperty('font-size', '14px', 'important');
                contentEl.style.setProperty('line-height', '1.5', 'important');
                contentEl.style.setProperty('font-weight', '400', 'important');
                contentEl.style.setProperty('letter-spacing', '0', 'important');
                contentEl.style.setProperty('color', sender === 'user' ? '#ffffff' : (this.config.botBubbleTextColor || '#000000'), 'important');
                contentEl.style.setProperty('background', sender === 'user' ? this.config.primaryColor : (this.config.botBubbleBgColor || '#f4f8f6'), 'important');
                contentEl.style.setProperty('white-space', 'normal', 'important');
            }
        }

        renderMessage(content, sender = 'user', timestamp = null, senderName = null) {
            const messagesContainer = document.getElementById(this.ids.messages);
            if (!messagesContainer) {
                console.error('AI Chat Widget: Messages container not found');
                return;
            }
            // Ensure messages container layout is not overridden by host CSS (e.g. reset.css margin:auto on divs)
            messagesContainer.style.setProperty('width', '100%', 'important');
            messagesContainer.style.setProperty('align-items', 'flex-start', 'important');

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
                <div class="ai-chat-message-time" style="margin:4px 0 0 0!important;padding:0!important;display:block!important;">${time}</div>
            `;
            this.applyMessageInlineStyles(messageElement, sender);

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
            messagesContainer.style.setProperty('width', '100%', 'important');
            messagesContainer.style.setProperty('align-items', 'flex-start', 'important');

            const messageElement = document.createElement('div');
            messageElement.className = 'ai-chat-message ai-chat-message-bot';
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            messageElement.innerHTML = `
                <div class="ai-chat-message-content"></div>
                <div class="ai-chat-message-time" style="margin:4px 0 0 0!important;padding:0!important;display:block!important;">${time}</div>
            `;
            this.applyMessageInlineStyles(messageElement, 'bot');
            const contentEl = messageElement.querySelector('.ai-chat-message-content');
            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // Type-out effect: append small chunks rapidly for perceived streaming
            const text = String(fullContent || '');
            let i = 0;
            const step = Math.max(2, Math.floor(text.length / 120)); // dynamic chunk size (~120 steps)
            const interval = 18; // ms per step

            const tick = () => {
                const next = text.slice(0, i += step);
                // Apply full formatting each tick so the animation always looks like
                // the final rendered output (bold, numbered lists, links, etc.)
                contentEl.innerHTML = this.linkify(next);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                if (i < text.length) {
                    setTimeout(tick, interval);
                }
            };
            tick();

            this.messages.push({ content: fullContent, sender: 'bot', timestamp: new Date() });
            // ISSUE 5B FIX: Persist messages after bot response
            this.saveMessages();
        }

        addTypingIndicator() {
            const messagesContainer = document.getElementById(this.ids.messages);
            if (!messagesContainer) return;
            messagesContainer.style.setProperty('width', '100%', 'important');
            messagesContainer.style.setProperty('align-items', 'flex-start', 'important');

            const typingElement = document.createElement('div');
            typingElement.className = 'ai-chat-message ai-chat-message-bot ai-chat-typing';
            typingElement.id = 'ai-chat-typing-' + this.config.orgId;
            this.applyMessageInlineStyles(typingElement, 'bot');
            
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
            
            // Focus on name input — desktop only; on mobile the keyboard should not pop up automatically
            const nameInput = document.getElementById(this.ids.leadName);
            if (nameInput && !this.isMobile()) nameInput.focus();
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
            if (input && !this.isMobile()) input.focus();
        }

        async submitLeadForm() {
            console.log('submitLeadForm called');
            const name = document.getElementById(this.ids.leadName).value.trim();
            const email = document.getElementById(this.ids.leadEmail).value.trim();
            const phone = document.getElementById(this.ids.leadPhone).value.trim();
            const customFieldValues = {};

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

            for (const field of this.contactFields) {
                const fieldId = this.ids.leadCustomFields?.[field.key];
                const inputEl = fieldId ? document.getElementById(fieldId) : null;
                const value = String(inputEl?.value || '').trim();

                if (field.required && !value) {
                    alert(`${field.label} is required to start chat.`);
                    if (inputEl) inputEl.focus();
                    return;
                }

                if (value) {
                    if (field.type === 'email' && !emailRegex.test(value)) {
                        alert(`Please enter a valid email for ${field.label}.`);
                        if (inputEl) inputEl.focus();
                        return;
                    }

                    if (field.type === 'phone') {
                        const fieldDigits = (value.match(/\d/g) || []).length;
                        if (!phoneRegex.test(value) || fieldDigits < 7 || fieldDigits > 15) {
                            alert(`Please enter a valid phone number for ${field.label}.`);
                            if (inputEl) inputEl.focus();
                            return;
                        }
                    }
                }

                customFieldValues[field.key] = value;
            }

            this.userInfo = { name, email, phone, custom_fields: customFieldValues };
            if (customFieldValues.location) {
                this.userInfo.location = customFieldValues.location;
                this.locationInfo = {
                    ...(this.locationInfo || {}),
                    location: customFieldValues.location
                };
            }
            this.leadCaptured = true;
            this.saveLeadCaptured();
            this.saveUserInfo();

            // Persist the lead to the server immediately, before any message is sent,
            // so visitors who fill the form but don't chat still appear in the Leads screen.
            try {
                fetch(`${this.config.apiUrl}/widget/${this.config.orgId}/lead`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({
                        session_id:    this.sessionId,
                        user_info:     this.userInfo,
                        location_info: this.locationInfo || {},
                        page_url:      window.location.href,
                        referrer:      document.referrer,
                        timezone:      Intl.DateTimeFormat().resolvedOptions().timeZone || '',
                    })
                }).catch(() => {}); // fire-and-forget; don't block the UX
            } catch (_e) {}

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
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            // Timezone-based baseline (always available, no network needed)
            const tzCity = timezone.includes('/') ? timezone.split('/').pop().replace(/_/g, ' ') : '';
            const tzRegion = timezone.includes('/') ? timezone.split('/')[0] : '';
            this.locationInfo = { timezone, city: tzCity, region: tzRegion, country: '' };

            try {
                // Proxy through our own server → no CSP issues
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 3000);
                const resp = await fetch('/widget/geoip', {
                    signal: controller.signal,
                    headers: { 'Accept': 'application/json' }
                });
                clearTimeout(timeout);
                if (resp.ok) {
                    const data = await resp.json();
                    if (data.city || data.country) {
                        this.locationInfo = {
                            timezone,
                            city: data.city || tzCity,
                            region: data.region || tzRegion,
                            country: data.country || '',
                        };
                    }
                }
            } catch (_e) {
                // Network error or timeout — timezone baseline already set above
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
            const launcherMode = String(this.config.widgetButtonBgType || 'gradient').toLowerCase();
            const launcherBackgroundRule = launcherMode === 'solid'
                ? `.ai-chat-button { background: ${colors.primaryColor} !important; }`
                : '';
            style.innerHTML = `
                ${launcherBackgroundRule}
                .ai-chat-header { background: ${colors.primaryColor} !important; }
                .ai-chat-message-user .ai-chat-message-content { background: ${colors.primaryColor} !important; }
                .ai-chat-send-button { background: ${colors.primaryColor} !important; }
                .ai-chat-lead-submit { background: ${colors.primaryColor} !important; }
                .ai-chat-input:focus { border-color: ${colors.primaryColor} !important; }
            `;
            document.head.appendChild(style);
        }

        applyCustomWidgetJs() {
            const customJs = String(this.config.customJs || '').trim();
            if (!customJs) {
                return;
            }

            try {
                const widget = document.getElementById(this.ids.widget);
                const run = new Function('widget', 'config', 'instance', customJs);
                run(widget, this.config, this);
            } catch (error) {
                console.error('[AI Widget] Custom JS execution failed:', error);
            }
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
                    referrer: data.referrer !== undefined ? data.referrer : (document.referrer || null),
                    user_agent: data.user_agent !== undefined ? data.user_agent : navigator.userAgent,
                    timestamp: new Date().toISOString(),
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
            if (!this.config.enableWebsocket) {
                return null;
            }
            return this.config.wsUrl || null;
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
                        <div class="ai-chat-message-time" style="margin:4px 0 0 0!important;padding:0!important;display:block!important;">${time}</div>
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
                    }, 8000)
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

        async sendMessage(forcedMessage = null) {
            if (!this.leadCaptured) {
                this.showLeadForm();
                return;
            }
            const input = document.getElementById(this.ids.input);
            if (!input) return;
            
            const message = (forcedMessage !== null ? String(forcedMessage) : input.value).trim();

            if (!message) return;

            this.removeStarterPrompts();

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

                // Plain HTTP JSON request - simple and reliable across all proxy/server configs
                const response = await fetch(`${this.config.apiUrl}/widget/${this.config.orgId}/chat`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestBody)
                });

                this.removeTypingIndicator();

                // Handle rate limiting
                if (response.status === 429) {
                    const data = await response.json();
                    const waitTime = data.retry_after || 60;
                    this.addMessage(`Please slow down! You can send up to 5 messages per minute. Please wait ${waitTime} seconds.`, 'bot');
                    return;
                }

                if (!response.ok) {
                    let errorText = '';
                    try {
                        errorText = await response.text();
                    } catch (readErr) {}
                    console.error('[AI Chat] Request failed:', { status: response.status, body: errorText });
                    const contactFallback = this.isContactQuery(message) ? this.buildContactResponse() : '';
                    if (contactFallback) {
                        this.addMessage(contactFallback, 'bot');
                    } else {
                        this.addMessage('Sorry, I encountered an error. Please try again.', 'bot');
                    }
                    return;
                }

                const jsonData = await response.json();
                const botResponse = jsonData.response || '';
                const hasContent = botResponse.trim().length > 0;

                if (hasContent) {
                    this.addStreamingMessage(botResponse);
                    if (this.isUnansweredResponse(botResponse)) {
                        this.trackAnalytics('unanswered_question', { message, response: botResponse });
                    }
                } else {
                    const contactFallback = this.isContactQuery(message) ? this.buildContactResponse() : '';
                    if (contactFallback) {
                        this.addMessage(contactFallback, 'bot');
                    } else {
                        this.addMessage('Sorry, I encountered an error. Please try again.', 'bot');
                    }
                }

            } catch (error) {
                console.error('Chat error:', {
                    name: error?.name,
                    message: error?.message,
                    stack: error?.stack
                });
                this.removeTypingIndicator();
                const contactFallback = this.isContactQuery(message) ? this.buildContactResponse() : '';
                if (contactFallback) {
                    this.addMessage(contactFallback, 'bot');
                } else {
                    this.addMessage('Sorry, I\'m experiencing technical difficulties. Please try again later.', 'bot');
                }
            }

            if (widget) {
                widget.addEventListener('click', (event) => {
                    const link = event.target?.closest?.('.ai-chat-attribution-link');
                    if (!link) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    const href = link.getAttribute('href') || 'https://ai-chat.support';
                    this.openAttributionLink(href);
                });
            }
        }

        openAttributionLink(url) {
            const targetUrl = String(url || 'https://ai-chat.support');

            try {
                const isDesignMode = !!(window.Shopify && window.Shopify.designMode);
                const opener = isDesignMode && window.top && window.top !== window ? window.top : window;
                const opened = opener.open(targetUrl, '_blank', 'noopener,noreferrer');

                if (opened) {
                    try {
                        opened.opener = null;
                    } catch (e) {}
                    return;
                }
            } catch (error) {
                console.debug('[AI Widget] openAttributionLink direct open failed:', error);
            }

            const temp = document.createElement('a');
            temp.href = targetUrl;
            temp.target = '_blank';
            temp.rel = 'noopener noreferrer';
            temp.style.display = 'none';
            document.body.appendChild(temp);
            temp.click();
            temp.remove();
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
