/* ==========================================================
    CLEAN AI CHAT WIDGET THEME (Refactored)
    ========================================================== */

/* Base */
.ai-chat-widget { position: fixed !important; z-index: 2147483647 !important; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif !important; --ai-offset-x: 20px; --ai-offset-y: 20px; color:#111111 !important; }
.ai-chat-widget, .ai-chat-widget * { font-family: inherit !important; box-sizing:border-box !important; }
.ai-chat-widget.bottom-right { bottom: var(--ai-offset-y) !important; right: var(--ai-offset-x) !important; }
.ai-chat-widget.bottom-left { bottom: var(--ai-offset-y) !important; left: var(--ai-offset-x) !important; }
.ai-chat-widget.top-right { top: var(--ai-offset-y) !important; right: var(--ai-offset-x) !important; }
.ai-chat-widget.top-left { top: var(--ai-offset-y) !important; left: var(--ai-offset-x) !important; }

/* Launcher Button */
.ai-chat-button { width:60px !important; height:60px !important; background:{{ $theme['launcherBackground'] ?? 'linear-gradient(135deg,#667eea,#764ba2)' }} !important; border:none !important; border-radius:50% !important; display:flex !important; align-items:center !important; justify-content:center !important; color:{{ $theme['iconColor'] ?? '#ffffff' }} !important; cursor:pointer !important; box-shadow:0 4px 14px rgba(0,0,0,.18) !important; transition:transform .25s ease, box-shadow .25s ease !important; position:relative !important; }
.ai-chat-button:hover { transform:scale(1.08) !important; box-shadow:0 6px 26px rgba(0,0,0,.25) !important; }
.ai-chat-button svg { width:28px !important; height:28px !important; fill:currentColor !important; color:{{ $theme['iconColor'] ?? '#ffffff' }} !important; display:block !important; max-width:none !important; max-height:none !important; min-width:0 !important; min-height:0 !important; flex-shrink:0 !important; }
.ai-chat-notification { position:absolute !important; top:-4px !important; right:-4px !important; width:20px !important; height:20px !important; background:#ff424d !important; color:#fff !important; border-radius:50% !important; font-size:11px !important; font-weight:600 !important; display:flex !important; align-items:center !important; justify-content:center !important; }

/* Window */
.ai-chat-window { position:absolute !important; bottom:calc(60px + 0px) !important; right:0 !important; width:427px !important; height:min(833px, calc(100dvh - (60px + var(--ai-offset-y) + 12px))) !important; max-height:calc(100dvh - (60px + var(--ai-offset-y) + 12px)) !important; background:#fff !important; border-radius:18px !important; box-shadow:0 18px 48px -8px rgba(20,20,40,.22),0 6px 18px -4px rgba(20,20,40,.18) !important; overflow:hidden !important; border:1px solid #e5e9ef !important; display:none !important; flex-direction:column !important; transition:all .3s ease !important; }
/* Adjust chat window vertical position depending on top/bottom with offset */
.ai-chat-widget.bottom-right .ai-chat-window,
.ai-chat-widget.bottom-left .ai-chat-window { bottom: calc(60px + var(--ai-offset-y)) !important; }
.ai-chat-widget.top-right .ai-chat-window,
.ai-chat-widget.top-left .ai-chat-window { top: calc(60px + var(--ai-offset-y)) !important; bottom: auto !important; }
.ai-chat-window.ai-chat-expanded { width:min(600px, calc(100vw - 24px)) !important; height:min(900px, calc(100dvh - (60px + var(--ai-offset-y) + 12px))) !important; }
@media (max-width:480px){ .ai-chat-window { width:calc(100vw - 24px) !important; height:calc(100dvh - 140px) !important; right:0 !important; bottom:84px !important; } }
@media (max-width:480px){ .ai-chat-window.ai-chat-expanded { width:calc(100vw - 12px) !important; height:calc(100dvh - 32px) !important; bottom:12px !important; } }
/* Mobile fullscreen: hide launcher, make window cover entire viewport */
@media (max-width:768px){
  .ai-chat-widget.ai-mobile-open .ai-chat-button { display:none !important; }
  .ai-chat-widget.ai-mobile-open .ai-chat-badge { display:none !important; }
  .ai-chat-widget.ai-mobile-open .ai-chat-window { position:fixed !important; top:0 !important; left:0 !important; right:0 !important; bottom:0 !important; width:100% !important; height:100% !important; height:100dvh !important; max-height:100% !important; border-radius:0 !important; }
}

/* Header */
.ai-chat-header { background:{{ $theme['primaryColor'] }} !important; color:#fff !important; padding:calc(18px + env(safe-area-inset-top)) 22px 18px 22px !important; display:flex !important; align-items:center !important; gap:14px !important; box-shadow:0 2px 4px rgba(0,0,0,.12) !important; }
.ai-chat-logo { width:36px !important; height:36px !important; border-radius:10px !important; background:rgba(255,255,255,.18) !important; display:flex !important; align-items:center !important; justify-content:center !important; overflow:hidden !important; flex-shrink:0 !important; }
.ai-chat-logo img { width:100% !important; height:100% !important; object-fit:cover !important; display:block !important; }
.ai-chat-header-info { flex:1 !important; min-width:0 !important; }
.ai-chat-header-actions { display:flex !important; gap:8px !important; }
.ai-chat-title { font-size:15px !important; font-weight:600 !important; letter-spacing:.2px !important; margin:0 0 4px 0 !important; }
.ai-chat-status { font-size:12px !important; display:flex !important; align-items:center !important; gap:6px !important; opacity:.95 !important; }
.ai-chat-status-dot { width:10px !important; height:10px !important; background:#16a34a !important; border-radius:50% !important; border:2px solid rgba(255,255,255,0.9) !important; box-shadow:0 0 0 3px rgba(22,163,74,.4) !important; animation:pulse 2.4s infinite !important; }
@keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(.6);opacity:.55} }
.ai-chat-expand, .ai-chat-close { background:rgba(255,255,255,.15) !important; border:none !important; color:#fff !important; width:34px !important; height:34px !important; border-radius:10px !important; display:flex !important; align-items:center !important; justify-content:center !important; cursor:pointer !important; transition:background .25s ease, transform .25s ease !important; }
.ai-chat-expand svg, .ai-chat-close svg { fill:currentColor !important; stroke:currentColor !important; color:#fff !important; }
.ai-chat-expand:hover, .ai-chat-close:hover { background:rgba(255,255,255,.28) !important; }
.ai-chat-close:hover { transform:rotate(90deg) !important; }

/* Scroll / Messages */
.ai-chat-messages { flex:1 !important; width:100% !important; padding:22px 22px 12px 22px !important; overflow-y:auto !important; display:flex !important; flex-direction:column !important; gap:14px !important; background:#ffffff !important; align-items:flex-start !important; }
.ai-chat-messages::-webkit-scrollbar { width:6px !important; }
.ai-chat-messages::-webkit-scrollbar-track { background:transparent !important; }
.ai-chat-messages::-webkit-scrollbar-thumb { background:#c3ccd6 !important; border-radius:3px !important; }

/* Message Blocks */
.ai-chat-message { max-width:82% !important; display:flex !important; flex-direction:column !important; margin:0 !important; padding:0 !important; border:0 !important; outline:0 !important; background:transparent !important; }
.ai-chat-message-user { align-self:flex-end !important; margin-left:auto !important; margin-right:0 !important; }
.ai-chat-message-bot { align-self:flex-start !important; margin-left:0 !important; margin-right:auto !important; }
.ai-chat-message-agent { align-self:flex-start !important; margin-left:0 !important; margin-right:auto !important; }
.ai-chat-message-content { padding:12px 15px !important; border-radius:0px 12px 12px !important; font-size:14px !important; line-height:1.5 !important; font-weight:400 !important; letter-spacing:0 !important; box-shadow:none !important; position:relative !important; overflow-wrap:break-word !important; word-break:break-word !important; }
.ai-chat-message-content, .ai-chat-message-content p, .ai-chat-message-content span, .ai-chat-message-content div, .ai-chat-message-content ul, .ai-chat-message-content ol, .ai-chat-message-content li, .ai-chat-message-content strong, .ai-chat-message-content em, .ai-chat-message-content a, .ai-chat-message-content code, .ai-chat-message-content pre, .ai-chat-message-content blockquote { font-family:inherit !important; font-size:inherit !important; line-height:inherit !important; letter-spacing:inherit !important; color:inherit !important; }
.ai-chat-message-content strong { font-weight:700 !important; }
.ai-chat-message-content em { font-style:italic !important; }
.ai-chat-message-content a { word-break:break-all !important; overflow-wrap:anywhere !important; text-decoration:underline !important; transition:color .2s ease !important; }
.ai-chat-message-bot .ai-chat-message-content a { color:#0b66d6 !important; font-weight:600 !important; }
.ai-chat-message-bot .ai-chat-message-content a:hover { color:#084ea8 !important; }
.ai-chat-message-user .ai-chat-message-content a { color:#e8f2ff !important; }
.ai-chat-message-user .ai-chat-message-content a:hover { color:#ffffff !important; }
.ai-chat-message-content p { margin:0 0 8px 0 !important; padding:0 !important; display:block !important; }
.ai-chat-message-content p:last-child { margin-bottom:0 !important; }
.ai-chat-message-content ul,.ai-chat-message-content ol { margin:4px 0 8px 0 !important; padding-left:20px !important; display:block !important; }
.ai-chat-message-content ul:last-child,.ai-chat-message-content ol:last-child { margin-bottom:0 !important; }
.ai-chat-message-content li { margin:2px 0 !important; padding:0 !important; display:list-item !important; }
.ai-chat-message-content ul > li { list-style-type:disc !important; }
.ai-chat-message-content ol > li { list-style-type:decimal !important; }
.ai-chat-message-content h1,.ai-chat-message-content h2,.ai-chat-message-content h3,.ai-chat-message-content h4,.ai-chat-message-content h5,.ai-chat-message-content h6 { margin:6px 0 3px !important; padding:0 !important; font-weight:700 !important; display:block !important; }
.ai-chat-message-content code { background:rgba(0,0,0,.07) !important; padding:1px 4px !important; border-radius:3px !important; font-size:13px !important; font-family:monospace !important; }
.ai-chat-message-content pre { background:rgba(0,0,0,.07) !important; padding:8px !important; border-radius:6px !important; overflow-x:auto !important; margin:6px 0 !important; display:block !important; }
.ai-chat-message-content blockquote { border-left:3px solid rgba(0,0,0,.2) !important; margin:6px 0 !important; padding:2px 0 2px 10px !important; opacity:.85 !important; display:block !important; }
.ai-chat-message-content img { max-width:100% !important; height:auto !important; border-radius:6px !important; cursor:pointer !important; margin:4px 0 !important; display:block !important; }
.ai-chat-message-user .ai-chat-message-content { background:{{ $theme['primaryColor'] }} !important; color:#ffffff !important; }
.ai-chat-message-bot .ai-chat-message-content { background:{{ $theme['botBubbleBgColor'] ?? '#f4f8f6' }} !important; color:{{ $theme['botBubbleTextColor'] ?? '#000000' }} !important; border:none !important; border-radius:0px 12px 12px !important; }
.ai-chat-message-agent .ai-chat-message-content { background:#f1f5ff !important; color:#1f2a44 !important; border:none !important; }
.ai-chat-message-sender { font-size:11px !important; font-weight:600 !important; margin-bottom:6px !important; color:#4b5a78 !important; }
.ai-chat-message-time { font-size:11px !important; color:#7a8594 !important; margin-top:4px !important; padding:0 4px !important; }

/* Typing Indicator */
.ai-chat-typing .ai-chat-message-content { background:#ffffff !important; border:1px solid #e3e7ec !important; }
.ai-chat-typing-dots { display:flex !important; gap:5px !important; }
.ai-chat-typing-dots span { width:7px !important; height:7px !important; background:#9aa4b1 !important; border-radius:50% !important; animation:typing 1.2s infinite ease-in-out !important; }
.ai-chat-typing-dots span:nth-child(2){ animation-delay:.2s !important; }
.ai-chat-typing-dots span:nth-child(3){ animation-delay:.4s !important; }
@keyframes typing { 0%,80%,100%{ transform:scale(.2); opacity:.4 } 40%{ transform:scale(1); opacity:1 } }

/* Starter prompt chips */
.ai-chat-starter-prompts { align-self:flex-start !important; max-width:92% !important; margin-top:4px !important; }
.ai-chat-starter-title { font-size:12px !important; color:#5d6b7a !important; margin:0 0 8px 2px !important; font-weight:600 !important; }
.ai-chat-starter-list { display:flex !important; flex-wrap:wrap !important; gap:8px !important; }
.ai-chat-starter-chip {
    border:1px solid #d7dee7 !important;
    background:#ffffff !important;
    color:#25313e !important;
    border-radius:999px !important;
    padding:7px 12px !important;
    font-size:12px !important;
    line-height:1.3 !important;
    cursor:pointer !important;
    transition:all .2s ease !important;
}
.ai-chat-starter-chip:hover {
    border-color:{{ $theme['primaryColor'] }} !important;
    color:{{ $theme['primaryColor'] }} !important;
    background:#f8fbff !important;
}

/* Input Area */
.ai-chat-input-container { padding:18px 20px 20px 20px !important; background:#ffffff !important; border-top:1px solid #e5e9ef !important; display:flex !important; align-items:flex-end !important; gap:12px !important; }
.ai-chat-input { flex:1 !important; border:1px solid #cfd6de !important; border-radius:14px !important; padding:11px 15px !important; font-size:14px !important; line-height:1.45 !important; min-height:44px !important; max-height:140px !important; resize:none !important; overflow-y:auto !important; background:#fff !important; color:#2f3640 !important; box-shadow:inset 0 1px 2px rgba(0,0,0,.04) !important; transition:border-color .2s ease, box-shadow .2s ease !important; }
.ai-chat-input:focus { border-color:{{ $theme['primaryColor'] }} !important; box-shadow:0 0 0 3px rgba(0,123,255,.15) !important; outline:none !important; }
.ai-chat-input::placeholder { color:#8c97a3 !important; }
.ai-chat-send-button { width:46px !important; height:46px !important; background:{{ $theme['primaryColor'] }} !important; border:none !important; border-radius:14px !important; display:flex !important; align-items:center !important; justify-content:center !important; color:#fff !important; cursor:pointer !important; transition:background .25s ease, transform .25s ease !important; box-shadow:0 4px 12px -2px rgba(0,123,255,.45) !important; }
.ai-chat-send-button svg { fill:currentColor !important; color:#fff !important; }
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

/* Utility - WordPress & Theme Compatibility */
.ai-chat-widget svg { pointer-events:none !important; display:inline-block !important; vertical-align:middle !important; max-width:none !important; max-height:none !important; min-width:0 !important; min-height:0 !important; overflow:visible !important; }
.ai-chat-widget svg path { vector-effect:non-scaling-stroke !important; }
.ai-chat-widget button svg { width:auto !important; height:auto !important; flex-shrink:0 !important; }
.ai-chat-widget svg path, .ai-chat-widget svg circle, .ai-chat-widget svg rect, .ai-chat-widget svg polygon { fill:inherit !important; }
.ai-chat-button svg path, .ai-chat-button svg circle { stroke:none !important; }
.ai-chat-button svg, .ai-chat-send-button svg, .ai-chat-expand svg, .ai-chat-close svg { width:initial !important; height:initial !important; display:block !important; }
.ai-chat-widget button { font-family:inherit !important; }

/* Branding footer */
.ai-chat-branding a { color: {{ $theme['primaryColor'] }} !important; }
    .ai-chat-branding img {
        height: 16px;
        width: auto;
        display: block;
    }

@if(!empty($customCss))

/* Organization-specific custom CSS */
{!! $customCss !!}

@endif

