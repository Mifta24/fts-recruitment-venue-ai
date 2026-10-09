# Knowledge Base

Table `venue_knowledge_items`. Each entry has `category`, `title`, `body`, optional `translations` (`en`, `ja`), `tags`, `image_url`, `is_active`, `sort_order`.

| Category | Shown on | Examples |
|---|---|---|
| `general` | Venue information | About the venue |
| `policies` | Venue information | Hours and load-in, package discounts, cancellation, branding and security |
| `faq` | Venue information | Hybrid events, deposits, own vendors |
| `facilities` | Facilities | Registration, Wi-Fi and power, stage and LED wall, CBT computers, interview pods, lockers |
| `catering` | Facilities | Catering and coffee breaks |
| `transport` | Facilities | MRT, Transjakarta, parking |

Facility icons are inferred from tags and title (`VenueKnowledgeItem::iconName()`). Editing happens in **Admin → Knowledge base**; only active entries are used by the coordinator and the site.
