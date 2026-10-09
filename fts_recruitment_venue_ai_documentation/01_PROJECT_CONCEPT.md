# FTS Recruitment Venue AI — Project Concept

## 1. Product
An interactive website for a **venue that is rented out for recruitment events**: job fairs, walk-in interviews, assessment (CBT) days, talent briefings and campus hiring. Organisers (HR teams, agencies, schools) explore the venue step by step, talk to an **AI Venue Coordinator**, check dates and prices, and submit a booking request. The venue team manages everything from an admin panel.

One application serves several venues. Every venue has its own page at `/{venueSlug}`.

## 2. Experience
The site is a **hiring pipeline**. Each section is one numbered step in a bar under the header:

| Step | Section | Purpose |
|---|---|---|
| 01 | Welcome | Hero, starting price, package saving, opening time |
| 02 | Spaces | Directory and detail pages of space types |
| 03 | Facilities | Equipment, registration, catering, transport |
| 04 | Venue information | About, house rules, FAQ, address |
| 05 | Book your event | Five-step booking wizard |
| 06 | Venue team | WhatsApp, phone, email, human handover |

Every step is one fixed, non-scrolling screen (`100dvh`): a venue photograph behind, the AI coordinator posed in front, rounded sheets on top, and the chat docked on the right.

## 3. Principles
- The AI never invents facts, prices or availability. Everything goes through tools backed by the database.
- The booking wizard and the AI use the same pricing and availability service, so they can never disagree.
- Three languages everywhere: Indonesian, English, Japanese.
- No payment is taken. The site only creates a **pending booking request** for staff to confirm.

## 4. Domain vocabulary
| Concept | Meaning |
|---|---|
| Venue | The recruitment venue (tenant) |
| Space type | A kind of space: job fair hall, interview suites, assessment lab, auditorium, boardroom |
| Inventory | Per space type and day: how many spaces are for rent, how many are booked, price |
| Booking | A request covering every day from start date to end date, **inclusive** |
| Candidates / recruiters | The expected crowd: participants and interviewers/organiser staff |
| Package discount | Multi-day (3+ days) and hiring-campaign (10+ days) percentages set per venue |
| Technical crew | Optional add-on (AV, CBT proctor, registration staff) charged per day |
