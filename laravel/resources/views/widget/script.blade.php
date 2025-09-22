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
            this.sessionId = this.generateSessionId();
            this.messages = [];
            this.welcomeShown = false; // Flag to track if welcome message was shown
            this.leadCaptured = this.checkLeadCaptured();
            this.userInfo = this.loadUserInfo();
            this.locationInfo = {};
            this.init();
            this.detectLocation();
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

        generateSessionId() {
            return 'session_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        }

        init() {
            this.loadStyles();
            this.createWidget();
            this.bindEvents();
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
                <div id="${widgetId}" class="ai-chat-widget ${this.config.position}">
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

                    <!-- Chat Window -->
                    <div id="${windowId}" class="ai-chat-window" style="display: none;">
                        <!-- Header -->
                        <div class="ai-chat-header">
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
                                    <button type="button" id="${leadSkipId}" class="ai-chat-lead-skip">Skip for now</button>
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
            
            if (leadSkip) {
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
            } else {
                window.style.setProperty('display', 'none', 'important');
                window.style.setProperty('visibility', 'hidden', 'important');
                button.style.transform = 'scale(1)';
            }
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

        addMessage(content, sender = 'user') {
            const messagesContainer = document.getElementById(this.ids.messages);
            if (!messagesContainer) {
                console.error('AI Chat Widget: Messages container not found');
                return;
            }
            
            const messageElement = document.createElement('div');
            messageElement.className = `ai-chat-message ai-chat-message-${sender}`;
            
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            
                               console.log('[AI Widget] Closing chat window');
            const safeContent = sender === 'bot' ? this.linkify(content) : this.linkify(content); // both user & bot for consistency
            messageElement.innerHTML = `
                <div class="ai-chat-message-content">
                    ${safeContent}
                </div>
                <div class="ai-chat-message-time">${time}</div>
            `;

            messagesContainer.appendChild(messageElement);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            this.messages.push({ content, sender, timestamp: new Date() });
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

        submitLeadForm() {
            console.log('submitLeadForm called');
            const name = document.getElementById(this.ids.leadName).value.trim();
            const email = document.getElementById(this.ids.leadEmail).value.trim();
            const phone = document.getElementById(this.ids.leadPhone).value.trim();

            if (!name || !email) {
                alert('Please fill in your name and email address.');
                return;
            }

            // Simple email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }

            this.userInfo = { name, email, phone };
            this.leadCaptured = true;
            this.saveLeadCaptured();
            this.saveUserInfo();
            
            // Store lead info (you can send this to server if needed)
            console.log('Lead captured:', this.userInfo);
            console.log('Calling hideLeadForm...');
            
            this.hideLeadForm();
            
            // Welcome message with name
            this.showWelcomeMessage(`Hello ${name}! ${this.config.welcomeMessage}`);
        }

        skipLeadForm() {
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
                fetch('{{ config("app.url") }}/analytics/track', {
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

        async sendMessage() {
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

                // Include lead information if captured
                if (this.leadCaptured && this.userInfo.name) {
                    requestBody.visitor_info = this.userInfo;
                }

                const response = await fetch(`${this.config.apiUrl}/widget/${this.config.orgId}/chat`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });

                const data = await response.json();

                // Remove typing indicator
                this.removeTypingIndicator();

                if (data.response) {
                    this.addMessage(data.response, 'bot');
                } else {
                    this.addMessage('Sorry, I couldn\'t process your message. Please try again.', 'bot');
                }

            } catch (error) {
                console.error('Chat error:', error);
                this.removeTypingIndicator();
                this.addMessage('Sorry, I\'m experiencing technical difficulties. Please try again later.', 'bot');
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
