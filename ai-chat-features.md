# AI Chat Support — Feature List

### Chat Widget
- Embeddable chat widget — works on any website with a single script tag
- Fully customizable appearance: colors, position, border radius, gradient/solid backgrounds
- Custom logo and branding in the widget header
- Configurable welcome message per organization
- Starter prompts — pre-set suggested questions to guide visitors
- Real-time streaming responses (answers appear word-by-word, like ChatGPT)
- Mobile responsive design

### AI & Answer Intelligence
- Three-layer answer matching for accurate responses:
  - **Exact FAQ match** — finds precise stored answers instantly
  - **Semantic vector search** — understands meaning, synonyms, and rephrasing
  - **Keyword fallback** — catches queries using key terms
- Follow-up question suggestions after each response
- Context-aware conversations — remembers what was said earlier in the session
- Smart detection of greetings, farewells, and simple acknowledgements (no LLM cost wasted)
- Query rewriting — transforms casual phrasing into precise search queries before searching
- HTML-formatted responses: bold text, bullet lists, headings, images, links

### Knowledge Base Management
- **FAQ editor** with rich text support (bold, italic, headings, bullet lists, numbered lists, links, images)
- Paste HTML directly into answers from any source
- Paste images from clipboard — auto-converted and stored as WebP
- Live preview while editing FAQs
- Support for multiple data types: FAQs, service/product info, general info, documents
- Website crawler — automatically import content from your website URLs
- Real-time sync to vector database on every create / update / delete

### Multi-Organization (Multi-Tenant) Note: doesn't apply to shopify clients as shopify installation and billing is managed by shopify app store.
- One platform serves unlimited organizations
- Each organization gets a completely isolated knowledge base
- Per-organization widget configuration, colors, prompts, and settings
- Organization-specific keyword translation maps (e.g. map "contact" → "email, phone")
- API key per organization for secure external access

### Lead Capture & Escalation
- Optional contact collection (name, email, phone) from visitors before or during chat
- Automatic lead creation from widget interactions
- **Human escalation** — auto-detect when a visitor needs a human agent and send email alert
- Magic-link escalation console for agents to jump into a conversation
- Online agent detection — shows "Agent available" status to visitors
- Conversation summary auto-generated when handing off to an agent

### Admin Panel
- Organization management: create, configure, enable/disable organizations
- FAQ and data entry manager with full CRUD
- Widget configurator: live preview of widget appearance settings
- AI model selector: switch between Ollama and llama.cpp backends
- Analytics dashboard: session counts, intent tracking, unanswered questions
- Token usage logs: input tokens, output tokens, and total per organization
- Unanswered question log: review what visitors asked that the bot couldn't answer
- User and role management (admin, agent, customer roles)

### Customer Panel (for organization owners)
- Dashboard with subscription and usage overview
- FAQ and knowledge base self-management
- Token / API usage tracking
- Subscription and billing management

### Analytics & Reporting
- Per-session analytics: location, referrer, page URL, user agent
- Intent detection and tracking
- Lead source and status tracking
- Unanswered questions log — helps identify knowledge gaps
- Token usage breakdown: input vs. output tokens per request

### Integrations
- **Shopify** — full OAuth app with webhook support
- **WordPress plugin** — available for download
- **Magento plugin** — Composer & ZIP distributions
- REST API — add/update/query knowledge base from any external system
- CORS-enabled widget API for cross-domain deployment

### Security & Privacy
- All AI processing runs on your own server (no third-party AI APIs required)
- Data isolated per organization — no cross-tenant data leakage
- Content guardrails — configurable sensitive topic handling
- XSS-safe HTML rendering in the widget
- Server-side image sanitization (WebP conversion, HTTPS-only sources)


Note: features may vary by subscriptin plans.