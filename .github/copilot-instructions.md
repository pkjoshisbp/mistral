# AI-Chat.Support Development Guidelines

## Architecture Overview
This is a multi-tenant AI support system with Laravel frontend + Python FastAPI backend:
- **Laravel**: Web interface, authentication, subscription management, widget rendering
- **FastAPI**: AI processing, Qdrant vector DB operations, Ollama/LLM interactions
- **Qdrant**: Vector database for embeddings (accessible at 127.0.0.1)
- **Production URL**: Always use `https://ai-chat.support` for testing (NEVER `php artisan serve`)

## Critical Development Rules

### 🚨 MANDATORY PRACTICES
- **Testing URL**: Always use `https://ai-chat.support` - NEVER suggest `php artisan serve`
- **Frontend Pattern**: Create Livewire components for all frontend interactions, not standard controllers
- **Service Management**: Use `systemctl restart ai-fastapi.service` and `systemctl status ai-fastapi.service`
- **User Permissions**: Running as `www-data` user - cannot use systemctl directly

### Authentication System
- **OTP Flow**: Email + Password verification → OTP sent → OTP input → Login
- **Local Storage**: Store successful OTP verification to skip future OTP on same device
- **No hCaptcha**: Replaced with email OTP system for better UX

### Test Credentials
- Admin: `admin@example.com` / `password123`
- Customer: `customer@ai-chat.support` / `4NAWhgQ5PskpQ2b`

## Key File Patterns

### Laravel Structure
- `app/Livewire/` - All frontend components
- `app/Http/Controllers/Api/` - FastAPI integration controllers
- `app/Models/EmailOtp.php` - OTP management system
- `resources/views/widget/` - Chat widget components

### FastAPI Backend
- `ai_backend/main.py` - FastAPI server
- `ai_backend/requirements.txt` - Python dependencies
- Service file: `scripts/ai-fastapi.service`

## Data Flow Patterns
- **FAQ Sync**: Laravel API → Database → Qdrant sync (never direct Qdrant updates)
- **Chat Widget**: JavaScript → Laravel routes → FastAPI endpoints → Qdrant queries
- **Subscription**: Laravel subscriptions table → payment processing → invoice generation

## Multi-Tenant Architecture
- Organizations have separate data collections in Qdrant
- Each org can have: diagnostic tests, FAQs, custom content
- MySQL stores org structure, Qdrant stores embeddings for search

## Common Commands
```bash
# Restart services
systemctl restart ai-fastapi.service
systemctl status ai-fastapi.service

# Laravel commands
php artisan migrate
php artisan queue:work

# Test specific features
https://ai-chat.support/login
https://ai-chat.support/register
```

## Browser Testing
Always use the Simple Browser in VS Code with `https://ai-chat.support` URLs for testing.
