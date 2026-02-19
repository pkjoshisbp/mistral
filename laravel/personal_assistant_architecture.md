# Personal Assistant – Voice Enabled Architecture

## Overview
This module enables a voice-first AI Personal Assistant inside the client panel.

The assistant will:
- Listen to user voice
- Transcribe using Whisper Large
- Understand intent
- Execute actions
- Respond via natural voice using XTTS / Indic TTS
- Learn user voice patterns over time

Goal: Move from Speech-to-Text → Speech-to-Action

---

## Core Components

### 1. Speech Recognition (Listening)
Model: Whisper Large (via Faster-Whisper)

Purpose:
- High multilingual accuracy
- Handles Indian English + Hinglish
- Supports adaptation layer via:
  - Vocabulary bias
  - Correction learning

---

### 2. Text Understanding
Model: LLM (existing ai-chat engine)

Purpose:
- Intent detection
- Command extraction
- Task routing

---

### 3. Speech Output (Speaking)
Models:
- XTTS v2 (Primary conversational voice)
- Indic TTS (Optional regional tone)

Use:
- Confirmation
- Assistant responses
- Notifications

---

## Training Flow (User Onboarding)

### Step 1 – Guided Reading
User reads provided sentences.
Captures:
- Accent
- Numbers
- Command tone

Stored as voice baseline.

---

### Step 2 – Free Speech
User speaks freely.
System:
- Shows transcription
- User edits or confirms

Corrections stored for future biasing.

---

### Step 3 – Custom Vocabulary
User adds:
- Names
- Business terms
- Brand words

Improves decoding accuracy.

---

### Step 4 – Command Simulation
User tries sample commands.
Assistant detects intent.
User confirms correctness.

---

### Step 5 – Continuous Learning
Whenever user edits transcription:
System updates:
- Pronunciation bias
- Word mapping

Correct once → Improve forever.

---

## Voice Interaction Flow

1. User clicks mic
2. Assistant listens
3. Transcription shown
4. User confirms / edits
5. Intent extracted
6. Action executed
7. Assistant responds via voice

---

## Supported Tasks (MVP)

### 1. Dictation
- Convert speech to editable text
- Email drafting
- Note writing

### 2. Send Email
Voice → Draft → Confirm → Send

### 3. Manage Google Calendar
- Create events
- Modify events
- Cancel events

### 4. Manage Appointments
- Internal booking system
- Client meetings

### 5. Reminders
- Time based
- Event based

### 6. Notes / Ideas
- Save quick thoughts
- Tag later

---

## Additional High-Value Tasks

### 7. Task Management
"Add task to follow up with client"

### 8. WhatsApp / SMS Drafting (Future)
Draft replies via voice

### 9. Daily Brief
Ask:
"What’s my day today?"

Assistant reads:
- Meetings
- Tasks
- Reminders

### 10. Quick Search
"Find last note about hotel project"

---

## System Architecture

Voice Input → Whisper Large → LLM → Intent Engine → Action Layer → TTS Output

---

## GPU Requirements
Runs on:
- 16GB RTX 4060 Ti

Concurrent services supported:
- Whisper Large
- XTTS
- LLM (7B Q4)

---

## Future Expansion

- Dedicated Mobile App
- Desktop Assistant
- Multi-language voice responses
- Personalized assistant voice

---

## End Vision

Assistant understands:
- Your voice
- Your language
- Your workflow

Moves from:
Speech Recognition → True Personal Assistant

