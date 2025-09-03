(function() {
    'use strict';
    
    // Widget Configuration
    const config = {!! json_encode($widgetConfig) !!};
    
    // Prevent multiple initializations
    if (window.AiChatWidget) {
        return;
    }

    class AiChatWidget {
        constructor(config) {
            this.config = config;
            this.isOpen = false;
            this.sessionId = this.generateSessionId();
            this.messages = [];
            this.leadCaptured = false;
            this.userInfo = {};
            this.init();
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
                            <button id="${closeId}" class="ai-chat-close">
                                <svg width="20" height="20" viewBox="0 0 20 20">
                                    <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>
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
                this.toggleWidget();
            });
            
            if (closeBtn) {
                closeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleWidget();
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

        toggleWidget() {
            const window = document.getElementById(this.ids.window);
            const button = document.getElementById(this.ids.button);
            
            if (!window || !button) {
                console.error('AI Chat Widget: Window or button not found');
                return;
            }
            
            this.isOpen = !this.isOpen;
            
            if (this.isOpen) {
                window.style.setProperty('display', 'flex', 'important');
                button.style.transform = 'scale(0.9)';
                
                // Check if user is logged in by looking for auth indicators
                const isLoggedIn = document.querySelector('meta[name="user-authenticated"]') || 
                                  document.body.classList.contains('logged-in') ||
                                  window.Laravel && window.Laravel.user;
                
                // Show lead form if not captured yet AND user is not logged in
                if (!this.leadCaptured && !isLoggedIn) {
                    this.showLeadForm();
                } else {
                    this.leadCaptured = true; // Skip lead capture for logged in users
                    const input = document.getElementById(this.ids.input);
                    if (input) {
                        input.focus();
                    }
                    
                    // Show welcome message if no messages yet
                    if (this.messages.length === 0) {
                        setTimeout(() => {
                            this.addMessage(this.config.welcomeMessage, 'bot');
                        }, 500);
                    }
                }
            } else {
                window.style.setProperty('display', 'none', 'important');
                button.style.transform = 'scale(1)';
            }
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
            
            messageElement.innerHTML = `
                <div class="ai-chat-message-content">
                    ${content}
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
            console.log('hideLeadForm called');
            const leadForm = document.getElementById(this.ids.leadForm);
            const messagesContainer = document.getElementById(this.ids.messages);
            const widget = document.getElementById(this.ids.widget);
            const inputContainer = widget ? widget.querySelector('.ai-chat-input-container') : null;
            
            console.log('leadForm:', leadForm);
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
                setTimeout(() => {
                    this.addMessage(this.config.welcomeMessage, 'bot');
                }, 500);
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
            
            // Store lead info (you can send this to server if needed)
            console.log('Lead captured:', this.userInfo);
            console.log('Calling hideLeadForm...');
            
            this.hideLeadForm();
            
            // Welcome message with name
            this.addMessage(`Hello ${name}! ${this.config.welcomeMessage}`, 'bot');
        }

        skipLeadForm() {
            this.leadCaptured = true;
            this.hideLeadForm();
        }

        async sendMessage() {
            const input = document.getElementById(this.ids.input);
            if (!input) return;
            
            const message = input.value.trim();

            if (!message) return;

            // Add user message
            this.addMessage(message, 'user');
            input.value = '';

            // Show typing indicator
            this.addTypingIndicator();

            try {
                const requestBody = {
                    message: message,
                    session_id: this.sessionId
                };

                // Include lead information if captured
                if (this.leadCaptured && this.userInfo.name) {
                    requestBody.user_info = this.userInfo;
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

    // Initialize widget
    window.AiChatWidget = new AiChatWidget(config);

})();
