# Booking Flow

## Rules (single source: `ReservationService`)
- A booking covers every day from `start_date` to `end_date`, inclusive. A one-day event starts and ends on the same date.
- Online requests cover up to **31 days**.
- Every day in range needs `total_spaces − booked_spaces ≥ requested spaces`.
- Capacity: `candidates ≤ max_candidates × spaces` and `candidates + recruiters ≤ (max_candidates + max_recruiters) × spaces`.
- Price = sum of daily inventory prices × spaces, plus technical crew (`tech_crew_price × days × spaces`) when requested and offered.
- Package discount on the subtotal: venue's multi-day percentage from 3 days, hiring-campaign percentage from 10 days.
- Creating a request holds the inventory immediately (status `pending`); cancelling gives it back. `cancelled` is final.

## Wizard (five steps)
Dates (calendar greys out fully booked days) → Crowd → Space → Details (name, company, event type, contact) → Summary with exact total.

## After submit
Reference `BK-XXXXXX`, staff email notification, organiser email if they gave an address (language follows theirs), WhatsApp / phone / email hand-over links.
