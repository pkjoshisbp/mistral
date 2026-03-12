# Mobile Assistant Flutter

Flutter app for **Personal Assistant-only** features of AI Chat Support.

## Included Features
- Email/password login via Sanctum token
- Assistant command console (type commands)
- Audio file upload + transcription
- Run assistant command and view AI reply
- Saved items list (notes/reminders/tasks/email drafts)
- Create, edit, delete saved items
- Profile settings (language, TTS provider, custom vocabulary, correction pairs)
- Training samples + save correction API integration

## Backend API Base
Set this in-app login screen:
- `https://ai-chat.support/api/mobile-assistant`

## Generate full Flutter platform folders
This repo stores Flutter code under this folder, but if platform folders are missing on server, run locally:

```bash
cd mobile-assistant-flutter
flutter create --platforms=android,ios,windows,macos .
flutter pub get
flutter run -d windows   # or macos/android/ios
```

## Notes
- App uses token auth (`Authorization: Bearer <token>`)
- Designed for clean, minimal workflow focused on personal assistant
- For server testing, keep API URL HTTPS
