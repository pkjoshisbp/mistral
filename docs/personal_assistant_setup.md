# Personal Assistant Voice Stack Setup

## What was added

> Scope: This Personal Assistant module is customer-panel only (`/customer/personal-assistant`) and is separate from the public website chat widget pipeline.

### Laravel
- Customer panel page: `/customer/personal-assistant`
- Livewire component: `App\\Livewire\\Customer\\PersonalAssistant`
- Voice profile persistence table: `personal_assistant_profiles`
- AI service methods in `AiAgentService`:
  - `transcribeAudio(...)`
  ````markdown
  # Personal Assistant Voice Stack Setup

  ## What was added

  ### Laravel
  - Customer panel page: `/customer/personal-assistant`
  - Livewire component: `App\\Livewire\\Customer\\PersonalAssistant`
  - Voice profile persistence table: `personal_assistant_profiles`
  - Assistant items table: `personal_assistant_items` (notes, reminders, tasks)
  - AI service methods in `AiAgentService`:
    - `transcribeAudio(...)`
    - `synthesizeSpeech(...)`
    - `parseAssistantCommand(...)`

  ### FastAPI (`ai_backend/main.py`)
  - `POST /voice/transcribe` (multipart audio)
  - `POST /voice/synthesize` (text-to-speech)
  - `POST /assistant/parse_command` (intent/action parsing)

  ### Personal Assistant actions now supported (MVP)
  - Save Notes from voice/text commands
  - Create Reminders from parsed entities/date text
  - Create Tasks from parsed commands
  - Generate Daily Brief from upcoming reminders/tasks
  - Quick Search across saved assistant items
  - Send Email action:
    - Creates draft when details are incomplete or confirmation is needed
    - Sends directly when parser marks it executable and recipient/body are valid

  ### Trial & subscription readiness
  - Personal Assistant access uses plan state in profile settings:
    - `assistant_plan_status`: `trial` or `active`
    - `assistant_trial_started_at`
    - `assistant_trial_ends_at`
  - New users get default free trial (14 days).
  - UI shows trial status and blocks assistant actions after trial expiry until subscription is active.

  Customer UI enhancements:
  - Action status after command execution
  - Saved items list (notes/reminders/tasks)
  - Persisted recent interaction history in profile settings
  - Onboarding-first flow with provided text samples (sentences + words/phrases)
  - Microphone capture for training samples (no manual voice file upload required)
  - Transcript -> edit/correct -> save loop with unlimited retries per sample
  - Assistant console unlocks after minimum verified onboarding samples

  ## Dedicated Vast.ai tunnel for Personal Assistant

  Use a separate autossh tunnel (independent of existing Ollama tunnel):

  ```bash
  bash /var/www/clients/client1/web64/web/scripts/start-personal-assistant-tunnel.sh
  ```

  This forwards:
  - `127.0.0.1:18081` -> Vast Whisper service
  - `127.0.0.1:18082` -> Vast XTTS service
  - `127.0.0.1:18083` -> Vast Indic TTS service

  ## Install and start voice services on Vast.ai

  ```bash
  bash /var/www/clients/client1/web64/web/scripts/setup-vast-personal-assistant.sh
  ```

  It installs and runs:
  - Whisper Large (`faster-whisper`) on port `18081`
  - XTTS v2 on port `18082`
  - Indic TTS service on port `18083`

  ## FastAPI environment variables (optional)

  Defaults already point to local tunnel ports.

  ```env
  PERSONAL_ASSISTANT_WHISPER_URL=http://127.0.0.1:18081/transcribe
  PERSONAL_ASSISTANT_XTTS_URL=http://127.0.0.1:18082/tts
  PERSONAL_ASSISTANT_INDIC_TTS_URL=http://127.0.0.1:18083/tts
  PERSONAL_ASSISTANT_TIMEOUT_SEC=60
  PERSONAL_ASSISTANT_MAX_AUDIO_MB=20
  LOCAL_WHISPER_MODEL=large-v3
  ```

  ## Local migration step

  ```bash
  cd /var/www/clients/client1/web64/web/laravel
  php artisan migrate
  ```

  ## Quick validation

  1. Open customer panel: `https://ai-chat.support/customer/personal-assistant`
  2. Save profile and vocabulary.
  3. Upload voice clip and click **Transcribe**.
  4. Click **Run Command** for intent parsing and action execution.
  5. Try commands like:
     - "Add note: call Rahul about the proposal"
     - "Remind me tomorrow at 10 AM to check reports"
     - "Add task to review pending invoices"
     - "What is my daily brief?"
     - "Find note about proposal"
    - "Send email to client@example.com about invoice follow-up"

  ````
