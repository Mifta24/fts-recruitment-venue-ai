# UI / UX Spec — "Talent Hall" theme

## Identity
- **Palette:** deep indigo `#14103a`, marigold `#ffb830`, coral `#ff5d73`, violet-tinted paper `#f1effa` / `#fbfaff`, teal `#19c3b1` for "online".
- **Type:** Plus Jakarta Sans for text and headings, DM Mono for small labels and numbers.
- **Shape:** rounded cards (14–22 px), pill buttons, soft shadows, dot-grid texture over photos.
- Admin panel shares the palette: Tailwind's `stone` scale is re-tinted to violet in `resources/css/app.css`.

## Layout
```text
┌──────────────────────────────────────────────────────────┐
│ brand                                      sound  ID EN JA│  header (68 px)
├──────────────────────────────────────────────────────────┤
│ HIRING PIPELINE   ①Welcome—②Spaces—③…—⑥Team    ● online   │  pipeline bar (60 px)
├───────────────────────────────────────┬──────────────────┤
│ content sheet / welcome / narrator    │ directory or chat│  stage
│                    (coordinator pose) │                  │
└───────────────────────────────────────┴──────────────────┘
```
- Visited steps are marked done; the current one is lit in marigold.
- On phones the pipeline bar scrolls sideways under the header and sheets fill the screen.
- Moving between pages: shutters close, the coordinator announces the destination, shutters open on the next photo.

## Components
Stage shell (`x-venue-stage`), pipeline bar, welcome poster with stat tiles, space directory board, content sheets (`hub-content`), narrator card (text-to-speech on demand), facility cards, info tabs, booking wizard with calendar, chat dock (launcher, log, composer, draft dialog), sound toggle, language switch.
