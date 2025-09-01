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
            // Create widget container
            const widgetHTML = `
                <div id="ai-chat-widget" class="ai-chat-widget ${this.config.position}">
                    <!-- Chat Button -->
                    <div id="ai-chat-button" class="ai-chat-button">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z" fill="white"/>
                            <circle cx="8" cy="10" r="1" fill="white"/>
                            <circle cx="12" cy="10" r="1" fill="white"/>
                            <circle cx="16" cy="10" r="1" fill="white"/>
                        </svg>
                        <span class="ai-chat-notification" id="ai-chat-notification">1</span>
                    </div>

                    <!-- Chat Window -->
                    <div id="ai-chat-window" class="ai-chat-window">
                        <!-- Header -->
                        <div class="ai-chat-header">
                            <div class="ai-chat-header-info">
                                <div class="ai-chat-title">${this.config.orgName}</div>
                                <div class="ai-chat-status">
                                    <span class="ai-chat-status-dot"></span>
                                    Online
                                </div>
                            </div>
                            <button id="ai-chat-close" class="ai-chat-close">
                                <svg width="20" height="20" viewBox="0 0 20 20">
                                    <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Messages -->
                        <div id="ai-chat-messages" class="ai-chat-messages">
                        </div>

                        <!-- Lead Capture Form -->
                        <div id="ai-chat-lead-form" class="ai-chat-lead-form" style="display: none;">
                            <div class="ai-chat-lead-content">
                                <h3>Let's get started!</h3>
                                <p>Please provide your details so we can assist you better:</p>
                                <div class="ai-chat-form-group">
                                    <input type="text" id="ai-lead-name" class="ai-chat-form-input" placeholder="Your Name *" required />
                                </div>
                                <div class="ai-chat-form-group">
                                    <input type="email" id="ai-lead-email" class="ai-chat-form-input" placeholder="Your Email *" required />
                                </div>
                                <div class="ai-chat-form-group">
                                    <input type="tel" id="ai-lead-phone" class="ai-chat-form-input" placeholder="Your Phone Number" />
                                </div>
                                <div class="ai-chat-form-actions">
                                    <button id="ai-chat-lead-submit" class="ai-chat-lead-submit">Start Chatting</button>
                                    <button id="ai-chat-lead-skip" class="ai-chat-lead-skip">Skip for now</button>
                                </div>
                            </div>
                        </div>

                        <!-- Input -->
                        <div class="ai-chat-input-container">
                            <input type="text" id="ai-chat-input" class="ai-chat-input" placeholder="Type your message..." />
                            <button id="ai-chat-send" class="ai-chat-send-button">
                                <svg width="20" height="20" viewBox="0 0 20 20">
                                    <path d="M2 10L18 2L11 10L18 18L2 10Z" fill="currentColor"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', widgetHTML);
        }

        bindEvents() {
            const button = document.getElementById('ai-chat-button');
            const closeBtn = document.getElementById('ai-chat-close');
            const sendBtn = document.getElementById('ai-chat-send');
            const input = document.getElementById('ai-chat-input');
            const leadSubmit = document.getElementById('ai-chat-lead-submit');
            const leadSkip = document.getElementById('ai-chat-lead-skip');

            button.addEventListener('click', () => this.toggleWidget());
            closeBtn.addEventListener('click', () => this.toggleWidget());
            sendBtn.addEventListener('click', () => this.sendMessage());
            leadSubmit.addEventListener('click', () => this.submitLeadForm());
            leadSkip.addEventListener('click', () => this.skipLeadForm());
            
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.sendMessage();
                }
            });

            // Hide notification when opened
            button.addEventListener('click', () => {
                document.getElementById('ai-chat-notification').style.display = 'none';
            });
        }

        toggleWidget() {
            const window = document.getElementById('ai-chat-window');
            const button = document.getElementById('ai-chat-button');
            
            this.isOpen = !this.isOpen;
            
            if (this.isOpen) {
                window.style.display = 'flex';
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
                    document.getElementById('ai-chat-input').focus();
                    
                    // Show welcome message if no messages yet
                    if (this.messages.length === 0) {
                        setTimeout(() => {
                            this.addMessage(this.config.welcomeMessage, 'bot');
                        }, 500);
                    }
                }
            } else {
                window.style.display = 'none';
                button.style.transform = 'scale(1)';
            }
        }

        addMessage(content, sender = 'user') {
            const messagesContainer = document.getElementById('ai-chat-messages');
            const messageElement = document.createElement('div');
            messageElement.className = `ai-chat-message ai-chat-message-${sender}`;
            
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
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
            const messagesContainer = document.getElementById('ai-chat-messages');
            const typingElement = document.createElement('div');
            typingElement.className = 'ai-chat-message ai-chat-message-bot ai-chat-typing';
            typingElement.id = 'ai-chat-typing';
            
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
            const typingElement = document.getElementById('ai-chat-typing');
            if (typingElement) {
                typingElement.remove();
            }
        }

        showLeadForm() {
            const leadForm = document.getElementById('ai-chat-lead-form');
            const messagesContainer = document.getElementById('ai-chat-messages');
            const inputContainer = document.querySelector('.ai-chat-input-container');
            
            leadForm.style.display = 'block';
            messagesContainer.style.display = 'none';
            inputContainer.style.display = 'none';
            
            // Focus on name input
            document.getElementById('ai-lead-name').focus();
        }

        hideLeadForm() {
            const leadForm = document.getElementById('ai-chat-lead-form');
            const messagesContainer = document.getElementById('ai-chat-messages');
            const inputContainer = document.querySelector('.ai-chat-input-container');
            
            leadForm.style.display = 'none';
            messagesContainer.style.display = 'flex';
            inputContainer.style.display = 'flex';
            
            // Show welcome message if no messages yet
            if (this.messages.length === 0) {
                setTimeout(() => {
                    this.addMessage(this.config.welcomeMessage, 'bot');
                }, 500);
            }
            
            document.getElementById('ai-chat-input').focus();
        }

        submitLeadForm() {
            const name = document.getElementById('ai-lead-name').value.trim();
            const email = document.getElementById('ai-lead-email').value.trim();
            const phone = document.getElementById('ai-lead-phone').value.trim();

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
            
            this.hideLeadForm();
            
            // Welcome message with name
            this.addMessage(`Hello ${name}! ${this.config.welcomeMessage}`, 'bot');
        }

        skipLeadForm() {
            this.leadCaptured = true;
            this.hideLeadForm();
        }

        async sendMessage() {
            const input = document.getElementById('ai-chat-input');
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
