/* ==========================================================
    CLEAN AI CHAT WIDGET THEME (Refactored)
    ========================================================== */

/* Base */
.ai-chat-widget { position: fixed !important; z-index: 999999 !important; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif !important; }
.ai-chat-widget.bottom-right { bottom: 20px !important; right: 20px !important; }
.ai-chat-widget.bottom-left { bottom: 20px !important; left: 20px !important; }
.ai-chat-widget.top-right { top: 20px !important; right: 20px !important; }
.ai-chat-widget.top-left { top: 20px !important; left: 20px !important; }

/* Launcher Button */
.ai-chat-button { width:60px !important; height:60px !important; background:linear-gradient(135deg,#667eea,#764ba2) !important; border:none !important; border-radius:50% !important; display:flex !important; align-items:center !important; justify-content:center !important; color:#fff !important; cursor:pointer !important; box-shadow:0 4px 14px rgba(0,0,0,.18) !important; transition:transform .25s ease, box-shadow .25s ease !important; position:relative !important; }
.ai-chat-button:hover { transform:scale(1.08) !important; box-shadow:0 6px 26px rgba(0,0,0,.25) !important; }
.ai-chat-notification { position:absolute !important; top:-4px !important; right:-4px !important; width:20px !important; height:20px !important; background:#ff424d !important; color:#fff !important; border-radius:50% !important; font-size:11px !important; font-weight:600 !important; display:flex !important; align-items:center !important; justify-content:center !important; }

/* Window */
.ai-chat-window { position:absolute !important; bottom:80px !important; right:0 !important; width:400px !important; height:560px !important; background:#fff !important; border-radius:18px !important; box-shadow:0 18px 48px -8px rgba(20,20,40,.22),0 6px 18px -4px rgba(20,20,40,.18) !important; display:none !important; overflow:hidden !important; border:1px solid #e5e9ef !important; display:flex !important; flex-direction:column !important; }
@media (max-width:480px){ .ai-chat-window { width:calc(100vw - 32px) !important; height:calc(100vh - 120px) !important; right:0 !important; bottom:90px !important; } }

/* Header */
.ai-chat-header { background:{{ $theme['primaryColor'] }} !important; color:#fff !important; padding:18px 22px !important; display:flex !important; align-items:center !important; gap:14px !important; box-shadow:0 2px 4px rgba(0,0,0,.12) !important; }
.ai-chat-header-info { flex:1 !important; min-width:0 !important; }
.ai-chat-title { font-size:15px !important; font-weight:600 !important; letter-spacing:.2px !important; margin:0 0 4px 0 !important; }
.ai-chat-status { font-size:12px !important; display:flex !important; align-items:center !important; gap:6px !important; opacity:.95 !important; }
.ai-chat-status-dot { width:8px !important; height:8px !important; background:#31d158 !important; border-radius:50% !important; box-shadow:0 0 0 4px rgba(49,209,88,.25) !important; animation:pulse 2.4s infinite !important; }
@keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(.6);opacity:.55} }
.ai-chat-close { background:rgba(255,255,255,.15) !important; border:none !important; color:#fff !important; width:34px !important; height:34px !important; border-radius:10px !important; display:flex !important; align-items:center !important; justify-content:center !important; cursor:pointer !important; transition:background .25s ease, transform .25s ease !important; }
.ai-chat-close:hover { background:rgba(255,255,255,.28) !important; transform:rotate(90deg) !important; }

/* Scroll / Messages */
.ai-chat-messages { flex:1 !important; padding:22px 22px 12px 22px !important; overflow-y:auto !important; display:flex !important; flex-direction:column !important; gap:14px !important; background:linear-gradient(180deg,#f7f9fb 0%,#f2f5f8 120%) !important; }
.ai-chat-messages::-webkit-scrollbar { width:6px !important; }
.ai-chat-messages::-webkit-scrollbar-track { background:transparent !important; }
.ai-chat-messages::-webkit-scrollbar-thumb { background:#c3ccd6 !important; border-radius:3px !important; }

/* Message Blocks */
.ai-chat-message { max-width:82% !important; display:flex !important; flex-direction:column !important; }
.ai-chat-message-user { align-self:flex-end !important; }
.ai-chat-message-bot { align-self:flex-start !important; }
.ai-chat-message-content { padding:12px 15px !important; border-radius:18px !important; font-size:14px !important; line-height:1.5 !important; box-shadow:0 1px 2px rgba(0,0,0,.08) !important; position:relative !important; }
.ai-chat-message-user .ai-chat-message-content { background:{{ $theme['primaryColor'] }} !important; color:#fff !important; }
.ai-chat-message-bot .ai-chat-message-content { background:#ffffff !important; color:{{ $theme['textColor'] }} !important; border:1px solid #e3e7ec !important; }
.ai-chat-message-time { font-size:11px !important; color:#7a8594 !important; margin-top:4px !important; padding:0 4px !important; }

/* Typing Indicator */
.ai-chat-typing .ai-chat-message-content { background:#ffffff !important; border:1px solid #e3e7ec !important; }
.ai-chat-typing-dots { display:flex !important; gap:5px !important; }
.ai-chat-typing-dots span { width:7px !important; height:7px !important; background:#9aa4b1 !important; border-radius:50% !important; animation:typing 1.2s infinite ease-in-out !important; }
.ai-chat-typing-dots span:nth-child(2){ animation-delay:.2s !important; }
.ai-chat-typing-dots span:nth-child(3){ animation-delay:.4s !important; }
@keyframes typing { 0%,80%,100%{ transform:scale(.2); opacity:.4 } 40%{ transform:scale(1); opacity:1 } }

/* Input Area */
.ai-chat-input-container { padding:18px 20px 20px 20px !important; background:#ffffff !important; border-top:1px solid #e5e9ef !important; display:flex !important; align-items:flex-end !important; gap:12px !important; }
.ai-chat-input { flex:1 !important; border:1px solid #cfd6de !important; border-radius:14px !important; padding:11px 15px !important; font-size:14px !important; line-height:1.45 !important; min-height:44px !important; max-height:140px !important; resize:none !important; overflow-y:auto !important; background:#fff !important; color:#2f3640 !important; box-shadow:inset 0 1px 2px rgba(0,0,0,.04) !important; transition:border-color .2s ease, box-shadow .2s ease !important; }
.ai-chat-input:focus { border-color:{{ $theme['primaryColor'] }} !important; box-shadow:0 0 0 3px rgba(0,123,255,.15) !important; outline:none !important; }
.ai-chat-input::placeholder { color:#8c97a3 !important; }
.ai-chat-send-button { width:46px !important; height:46px !important; background:{{ $theme['primaryColor'] }} !important; border:none !important; border-radius:14px !important; display:flex !important; align-items:center !important; justify-content:center !important; color:#fff !important; cursor:pointer !important; transition:background .25s ease, transform .25s ease !important; box-shadow:0 4px 12px -2px rgba(0,123,255,.45) !important; }
.ai-chat-send-button:hover { background:rgba(0,123,255,.9) !important; transform:translateY(-2px) !important; }
.ai-chat-send-button:active { transform:translateY(0) !important; box-shadow:0 2px 6px rgba(0,0,0,.25) !important; }
.ai-chat-send-button:disabled { background:#9aa4b1 !important; box-shadow:none !important; cursor:not-allowed !important; }

/* Lead Form */
.ai-chat-lead-form { padding:34px 28px 28px 28px !important; background:#ffffff !important; overflow-y:auto !important; }
.ai-chat-lead-content h3 { font-size:26px !important; font-weight:700 !important; margin:0 0 14px 0 !important; color:#0f1d2b !important; letter-spacing:.3px !important; }
.ai-chat-lead-content p { font-size:15px !important; color:#3a4652 !important; margin:0 0 26px 0 !important; line-height:1.5 !important; }
.ai-chat-form-group { margin:0 0 14px 0 !important; }
.ai-chat-form-input { width:100% !important; padding:13px 16px !important; border:1px solid #cfd6de !important; border-radius:10px !important; background:#fdfdff !important; font-size:14px !important; transition:border-color .25s ease, box-shadow .25s ease !important; font-family:inherit !important; }
.ai-chat-form-input:focus { outline:none !important; border-color:{{ $theme['primaryColor'] }} !important; box-shadow:0 0 0 3px rgba(0,123,255,.15) !important; background:#fff !important; }
.ai-chat-form-input::placeholder { color:#96a1ad !important; }
.ai-chat-form-actions { display:flex !important; gap:12px !important; margin-top:6px !important; }
.ai-chat-lead-submit { flex:1 !important; padding:13px 18px !important; background:{{ $theme['primaryColor'] }} !important; color:#fff !important; border:none !important; border-radius:10px !important; font-weight:600 !important; cursor:pointer !important; font-size:14px !important; letter-spacing:.3px !important; transition:background .25s ease, transform .25s ease !important; }
.ai-chat-lead-submit:hover { background:rgba(0,123,255,.9) !important; transform:translateY(-2px) !important; }
.ai-chat-lead-skip { padding:13px 16px !important; background:#eef2f6 !important; color:#44505c !important; border:none !important; border-radius:10px !important; font-size:13px !important; cursor:pointer !important; transition:background .25s ease,color .25s ease !important; }
.ai-chat-lead-skip:hover { background:#e1e7ec !important; color:#1a2632 !important; }
.ai-chat-lead-form[style*='display: none'] { display:none !important; }

/* Utility */
.ai-chat-widget svg { pointer-events:none !important; }
.ai-chat-widget button { font-family:inherit !important; }
.ai-chat-widget * { box-sizing:border-box !important; }

