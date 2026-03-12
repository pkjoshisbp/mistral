# Mobile Assistant API Usage

Base URL:
- `https://ai-chat.support/api/mobile-assistant`

## Auth
### Login
`POST /login`
```json
{
  "email": "customer@ai-chat.support",
  "password": "******",
  "device_name": "my-phone"
}
```
Response includes `token`.

### Auth header for all protected APIs
`Authorization: Bearer <token>`

### Logout
`POST /logout`

## Profile & Settings
- `GET /me`
- `GET /settings`
- `PUT /settings`

## Training
- `GET /training/samples?mode=sentences|phrases|paragraphs`
- `POST /training/save-correction`

## Voice + Command
- `POST /voice/transcribe` (multipart with `audio` file)
- `POST /commands/process`

## Saved Items CRUD
- `GET /items`
- `POST /items`
- `PUT /items/{id}`
- `DELETE /items/{id}`

## Quick curl example
```bash
curl -X POST https://ai-chat.support/api/mobile-assistant/login \
  -H "Content-Type: application/json" \
  -d '{"email":"customer@ai-chat.support","password":"<password>","device_name":"test-device"}'
```
