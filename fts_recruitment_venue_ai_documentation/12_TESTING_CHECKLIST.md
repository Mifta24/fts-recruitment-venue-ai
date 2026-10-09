# Testing Checklist

Run `php artisan test` (PHPUnit). Manual checks:

- [ ] Opening screen lists published venues only; draft venues return 404.
- [ ] Pipeline bar highlights the current step and marks earlier ones done, on desktop and phone.
- [ ] Spaces directory lists active space types of this venue only; detail page shows layouts, capacity, campaign rate.
- [ ] Booking wizard: one-day event (same start and end), multi-day discount at 3 days, campaign discount at 10 days, 31-day limit.
- [ ] Fully booked days are struck out; a range cannot cross them.
- [ ] Company is required; event type is validated.
- [ ] A request holds inventory; cancelling releases it exactly once.
- [ ] Coordinator: knowledge only via tool, refuses off-topic and offensive input, answers in the user's language, hands over to staff.
- [ ] Emails: staff notified, organiser acknowledged in their language, WhatsApp-only organisers get no email.
- [ ] Admin: staff only see their own venue's data; settings are owner-only.
- [ ] All three languages render without missing labels.
