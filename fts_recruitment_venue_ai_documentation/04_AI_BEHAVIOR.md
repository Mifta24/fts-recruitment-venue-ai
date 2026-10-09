# AI Behaviour

The **AI Venue Coordinator** (`CoordinatorService`) talks to a local OpenAI-compatible endpoint (LM Studio / Ollama) and runs a tool-calling loop of at most six rounds per turn, bounded to 85 seconds.

## Rules baked into the system prompt
1. Reply in the language of the user's latest message (id / en / ja).
2. Venue facts only from `search_knowledge`.
3. Prices and availability only from `search_spaces` / `check_availability`.
4. Tool results render as cards on screen; the reply is a short comment.
5. Before `create_booking_request`: space, start and end date, candidates (and recruiters), organiser name, company, phone.
6. Hand over to staff for special requests, complaints, custom set-ups, negotiated rates, long events, unusual cancellations, payment issues, low confidence.
7. Stay in scope: only this venue. No candidate screening, job ads, HR/legal/salary advice, general chat, role-play.
8. Never produce rude, sexual, illegal or discriminatory content.

## Deterministic guards
- `ContentGuard` answers offensive messages with a fixed refusal in the user's language and replaces offensive model output.
- Replies that state a price or placeholder without a tool call are challenged once.

## UI awareness
The chat sends the current scene (`hub`, `spaces`, `space_detail`, `facilities`, `facility_detail`, `reservation`, `handover`), the selected space or facility, and the booking draft (never personal data).
