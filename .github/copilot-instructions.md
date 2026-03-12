# AI Agent Multi-Tenant Chat System - Copilot Instructions

> **IMPORTANT:** Before assuming anything about ports, GPU, installed tools, or
> service locations — read the full reference first:
> **[docs/PROJECT_SETUP.md](../docs/PROJECT_SETUP.md)**
>
> Key facts to internalize:
> - **GPU is on vast.ai, NOT local.** `nvidia-smi` will fail on this server.
> - **ComfyUI, large Ollama LLMs, Whisper, XTTS all run on vast.ai via SSH tunnels.**
> - **FastAPI venv is at `ai_backend/venv/`** — always use `venv/bin/pip` to install packages.
> - **SadTalker / Wav2Lip are NOT installed** anywhere (as of 2026-03-09).

## System Architecture

This is a **multi-organization AI support agent system** with three main components:

1. **Laravel Frontend** (`/laravel/`) - Multi-tenant web application with Livewire components
2. **FastAPI Backend** (`/ai_backend/`) - AI processing, embeddings, and LLM integration  
3. **Qdrant Vector DB** - Document storage and semantic search (127.0.0.1:6333)
4. **AI Models** - Ollama (llama3.2:3b) or llama.cpp backends
5. **Embeddings** - nomic-embed-text for 768-dim vectors
6. **Widget Integration** - Embeddable chat widget for customer websites
7. **Database** - MySQL for relational data and organization management
8. **Systemd Service** - `ai-fastapi.service` for managing FastAPI lifecycle
9. **Token Usage Tracking** - Monitors API usage and costs per organization
10. **Admin Panel** - Organization and user management, AI settings
11. **Data Sync** - Real-time sync of org data to Qdrant on CRUD operations
12. **fastapi url** - `http://localhost:8111` for internal API calls
13. **Qdrant URL** - `http://localhost:6333` for vector database access
14. **Widget URL** - `https://ai-chat.support/widget/{org_slug}/script.js` for embedding
15. **Testing Environment** - `https://ai-chat.support` for staging and testing
16. **webroot is set to /var/www/ai-chat-support/laravel/public for nginx/apache**
17. **Always use bootstrap classes for styling - no tailwind CSS**
### Key Services Integration
- **AI Models**: Supports both Ollama (llama3.2:3b) and llama.cpp backends
- **Embeddings**: nomic-embed-text for consistent 768-dimensional vectors
- **Organization Isolation**: Each org gets its own Qdrant collection (`{org_slug}`)

## Critical Developer Workflows

### Service Management (Ubuntu 22.04)
```bash
# Restart AI services (common debugging step)
systemctl restart ai-fastapi.service
systemctl status ai-fastapi.service

# FastAPI runs on localhost:8111, logs to ai_backend/fastapi.log
```

### Data Sync Commands
```bash
# Sync organization data to Qdrant vector DB
php artisan sync:organization-data [org_id] [--type=faq|info|service|all]

# Test AI chat functionality  
php artisan test:ai-chat [org_slug]
```

### Testing Environment
- **Base URL**: https://ai-chat.support (never use `php artisan serve`)
- **Admin Login**: info@ai.chat.support / password123
- **Customer Login**: customer@ai-chat.support / pragati123..

## Laravel-Specific Patterns

### Livewire-First Architecture
**Always use Livewire components** for frontend interactions, not controllers+views:
```php
// app/Livewire/DataEntryManager.php - handles CRUD with auto-sync
// app/Livewire/AiChat.php - main chat interface
// app/Livewire/Admin/ - admin management components
```

### Organization-Scoped Models
```php
// All data is organization-scoped via relationships
Organization::find($id)->organizationData() // FAQ, Info, Services
Organization::find($id)->users() // Many-to-many via pivot
```

### AI Service Integration Pattern
```php
// app/Services/AiAgentService.php - unified AI provider abstraction
$aiService = app(AiAgentService::class);
$response = $aiService->chat($message, $organizationSlug);
```

## Data Flow & Sync Architecture

### Laravel → FastAPI → Qdrant Pipeline
1. **Data Entry**: Livewire components (DataEntryManager, etc.)
2. **Auto-Sync**: Immediate sync to Qdrant after create/update/delete
3. **Search**: FastAPI `/search` with query rewriting via Llama-3.2
4. **Chat**: FastAPI `/chat` with context from vector search

### Collection Naming Convention
- Qdrant collections: `{organization_slug}` (e.g., "ai-chat-support")
- Point IDs: `{data_type}_{item_id}` (e.g., "faq_123", "info_456")

## Configuration & Environment

### Multi-AI Backend Support
```php
// config/services.php
'ai_agent' => ['url' => 'http://localhost:8111'],

// Admin settings override config:
AdminSetting::get('ai_backend_type') // 'ollama' or 'llamacpp'
AdminSetting::get('ai_model_provider') // 'llama' or 'openai'
```

### Widget Integration
- **Script Generation**: `/widget/{org_slug}/script.js`
- **Embed Code**: Organizations get unique widget scripts
- **Cross-Origin**: CORS configured for multi-domain deployment

## Performance & Debugging

### Common Issues & Solutions
- **Qdrant Connection**: Check 127.0.0.1:6333 accessibility
- **Model Loading**: Ensure models are pulled/downloaded before service start
- **Sync Failures**: Check FastAPI logs in `ai_backend/fastapi.log`
- **Widget Loading**: Verify organization slug and API endpoints

### Monitoring Points
- Token usage tracked in `token_usage_logs` table
- Chat sessions in `chat_sessions` and `chat_messages` tables  
- Analytics data captured per organization

## Key File Locations

### Core Application Logic
- `laravel/app/Services/AiAgentService.php` - AI provider abstraction
- `laravel/app/Services/UnifiedSyncService.php` - Qdrant sync orchestration
- `ai_backend/main.py` - FastAPI endpoints and AI processing

### Data Models & Migrations
- `laravel/app/Models/Organization.php` - Multi-tenant base model
- `laravel/database/migrations/` - Schema evolution (60+ migrations)

### Frontend Components  
- `laravel/app/Livewire/` - All interactive UI components
- `laravel/resources/views/widget/` - Embeddable widget templates

## Customer Reviews System

### Review Workflow
- **Customer Submission**: Authenticated users can submit reviews via `/reviews/submit`
- **Admin Moderation**: All reviews require admin approval before being published
- **Public Display**: Approved reviews shown on `/reviews` with filtering and pagination

### Review Components
```php
// app/Livewire/Customer/ReviewForm.php - Review submission form
// app/Livewire/Admin/ReviewManager.php - Admin moderation interface
// app/Livewire/Public/ReviewsDisplay.php - Public reviews display
```

### Review Model & Relationships
```php
CustomerReview::approved()->featured() // Get featured approved reviews
Organization::find($id)->averageRating // Auto-calculated average rating
User::find($id)->customerReviews() // User's submitted reviews
```

This system prioritizes **multi-tenant isolation**, **real-time sync**, **flexible AI backend switching**, and **moderated customer feedback** - keep these principles in mind when extending functionality.