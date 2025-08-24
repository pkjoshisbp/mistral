/* AI Chat Widget Styles */
.ai-chat-widget {
    position: fixed !important;
    z-index: 999999 !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
}

.ai-chat-widget.bottom-right {
    bottom: 20px !important;
    right: 20px !important;
}

.ai-chat-widget.bottom-left {
    bottom: 20px !important;
    left: 20px !important;
}

.ai-chat-widget.top-right {
    top: 20px !important;
    right: 20px !important;
}

.ai-chat-widget.top-left {
    top: 20px !important;
    left: 20px !important;
}

/* Chat Button */
.ai-chat-button {
    width: 60px !important;
    height: 60px !important;
    background: {{ $theme['primaryColor'] }} !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
    transition: all 0.3s ease !important;
    position: relative !important;
    border: none !important;
    outline: none !important;
}

.ai-chat-button:hover {
    transform: scale(1.1) !important;
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.2) !important;
}

.ai-chat-notification {
    position: absolute !important;
    top: -5px !important;
    right: -5px !important;
    background: #ff4444 !important;
    color: white !important;
    border-radius: 50% !important;
    width: 20px !important;
    height: 20px !important;
    font-size: 12px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-weight: bold !important;
}

/* Chat Window */
.ai-chat-window {
    position: absolute !important;
    bottom: 80px !important;
    right: 0 !important;
    width: 380px !important;
    height: 500px !important;
    background: white !important;
    border-radius: {{ $theme['borderRadius'] }} !important;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15) !important;
    display: none !important;
    flex-direction: column !important;
    overflow: hidden !important;
    border: 1px solid #e1e5e9 !important;
}

@media (max-width: 420px) {
    .ai-chat-window {
        width: calc(100vw - 40px) !important;
        height: calc(100vh - 140px) !important;
        bottom: 80px !important;
        right: -10px !important;
    }
}

/* Header */
.ai-chat-header {
    background: {{ $theme['primaryColor'] }} !important;
    color: white !important;
    padding: 16px 20px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
}

.ai-chat-header-info {
    flex: 1 !important;
}

.ai-chat-title {
    font-size: 16px !important;
    font-weight: 600 !important;
    margin-bottom: 4px !important;
}

.ai-chat-status {
    font-size: 12px !important;
    opacity: 0.9 !important;
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
}

.ai-chat-status-dot {
    width: 8px !important;
    height: 8px !important;
    background: #4ade80 !important;
    border-radius: 50% !important;
    animation: pulse 2s infinite !important;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.ai-chat-close {
    background: none !important;
    border: none !important;
    color: white !important;
    cursor: pointer !important;
    padding: 8px !important;
    border-radius: 4px !important;
    transition: background 0.2s ease !important;
}

.ai-chat-close:hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

/* Messages */
.ai-chat-messages {
    flex: 1 !important;
    overflow-y: auto !important;
    padding: 20px !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 16px !important;
    background: #f8f9fa !important;
}

.ai-chat-messages::-webkit-scrollbar {
    width: 4px !important;
}

.ai-chat-messages::-webkit-scrollbar-track {
    background: transparent !important;
}

.ai-chat-messages::-webkit-scrollbar-thumb {
    background: #cbd5e0 !important;
    border-radius: 2px !important;
}

.ai-chat-message {
    display: flex !important;
    flex-direction: column !important;
    max-width: 80% !important;
}

.ai-chat-message-user {
    align-items: flex-end !important;
    margin-left: auto !important;
}

.ai-chat-message-bot {
    align-items: flex-start !important;
    margin-right: auto !important;
}

.ai-chat-message-content {
    padding: 12px 16px !important;
    border-radius: 18px !important;
    word-wrap: break-word !important;
    line-height: 1.4 !important;
    font-size: 14px !important;
}

.ai-chat-message-user .ai-chat-message-content {
    background: {{ $theme['primaryColor'] }} !important;
    color: white !important;
}

.ai-chat-message-bot .ai-chat-message-content {
    background: white !important;
    color: {{ $theme['textColor'] }} !important;
    border: 1px solid #e1e5e9 !important;
}

.ai-chat-message-time {
    font-size: 11px !important;
    color: #8b949e !important;
    margin-top: 4px !important;
    padding: 0 8px !important;
}

/* Typing Indicator */
.ai-chat-typing .ai-chat-message-content {
    padding: 16px !important;
}

.ai-chat-typing-dots {
    display: flex !important;
    gap: 4px !important;
}

.ai-chat-typing-dots span {
    width: 8px !important;
    height: 8px !important;
    background: #8b949e !important;
    border-radius: 50% !important;
    animation: typing 1.4s infinite ease-in-out !important;
}

.ai-chat-typing-dots span:nth-child(1) { animation-delay: 0s !important; }
.ai-chat-typing-dots span:nth-child(2) { animation-delay: 0.2s !important; }
.ai-chat-typing-dots span:nth-child(3) { animation-delay: 0.4s !important; }

@keyframes typing {
    0%, 60%, 100% {
        transform: translateY(0) !important;
        opacity: 0.3 !important;
    }
    30% {
        transform: translateY(-10px) !important;
        opacity: 1 !important;
    }
}

/* Input */
.ai-chat-input-container {
    padding: 16px 20px !important;
    background: white !important;
    border-top: 1px solid #e1e5e9 !important;
    display: flex !important;
    gap: 12px !important;
    align-items: center !important;
}

.ai-chat-input {
    flex: 1 !important;
    border: 1px solid #e1e5e9 !important;
    border-radius: 20px !important;
    padding: 10px 16px !important;
    font-size: 14px !important;
    outline: none !important;
    transition: border-color 0.2s ease !important;
    font-family: inherit !important;
    background: white !important;
    color: {{ $theme['textColor'] }} !important;
}

.ai-chat-input:focus {
    border-color: {{ $theme['primaryColor'] }} !important;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1) !important;
}

.ai-chat-input::placeholder {
    color: #8b949e !important;
}

.ai-chat-send-button {
    width: 40px !important;
    height: 40px !important;
    background: {{ $theme['primaryColor'] }} !important;
    border: none !important;
    border-radius: 50% !important;
    color: white !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: background 0.2s ease !important;
    outline: none !important;
}

.ai-chat-send-button:hover {
    background: color-mix(in srgb, {{ $theme['primaryColor'] }} 90%, black 10%) !important;
}

.ai-chat-send-button:disabled {
    opacity: 0.5 !important;
    cursor: not-allowed !important;
}

/* Reset conflicting styles */
.ai-chat-widget * {
    box-sizing: border-box !important;
    margin: 0 !important;
    padding: 0 !important;
}
