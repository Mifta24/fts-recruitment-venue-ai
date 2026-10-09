# Prompt System

`CoordinatorService::buildSystemPrompt()` assembles:

1. **Role:** AI Venue Coordinator of the venue, for recruitment events.
2. **Hard rules** (language, knowledge via tools, prices via tools, booking prerequisites, handover triggers, scope, safety).
3. **Package line:** the venue's multi-day and campaign percentages, or a note that none exist.
4. **Currency and today's date** in the venue's time zone.
5. **UI context:** scene, selected space and facility, booking draft, and scene guidance.

Each request also appends a short **scope reminder** after the user's last message (never stored). Tool definitions live in `VenueCoordinatorTools::definitions()`:
`search_knowledge`, `search_spaces`, `get_space_detail`, `check_availability`, `create_booking_request`, `request_human_handover`.
