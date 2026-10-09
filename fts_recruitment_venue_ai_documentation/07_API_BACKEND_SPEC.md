# Routes & Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/` | Opening screen |
| GET | `/{venueSlug}` | 01 Welcome |
| GET | `/{venueSlug}/spaces`, `/spaces/{spaceSlug}` | 02 Spaces |
| GET | `/{venueSlug}/facilities`, `/facilities/{id}` | 03 Facilities |
| GET | `/{venueSlug}/info` | 04 Venue information |
| GET | `/{venueSlug}/reservation` | 05 Booking wizard (`?space=slug` preselects) |
| GET | `/{venueSlug}/staff` | 06 Venue team |
| GET | `/{venueSlug}/reservation/availability` | Days that still have a free space |
| POST | `/{venueSlug}/reservation/quote` | Exact price and availability |
| POST | `/{venueSlug}/reservation` | Create a pending booking request |
| POST | `/{venueSlug}/coordinator/start` | Start a conversation |
| POST | `/{venueSlug}/coordinator/message` | Send a message |
| GET | `/{venueSlug}/coordinator/history` | Conversation history |

Admin lives under `/admin` (login, dashboard, space types and inventory, knowledge items, bookings, handovers, settings). Language is chosen with `?lang=id|en|ja`.

Booking payload: `space_type_slug, start_date, end_date, candidates, recruiters, spaces, tech_crew, guest_name, company_name, event_type, contact_type, contact_value, special_request, locale`. Event types: `job_fair, walk_in_interview, assessment, briefing, campus_hiring, other`.

Rate limits: reservation endpoints 20/min, coordinator start/message/history have their own limiters (`AppServiceProvider`), admin login 5/min.
