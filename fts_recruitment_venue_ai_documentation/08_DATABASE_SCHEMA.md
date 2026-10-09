# Database Schema

| Table | Key columns |
|---|---|
| `venues` | name, slug, address, city, country, lat/lng, phone, whatsapp, email, timezone, currency, default_locale, `open_time`, `close_time`, `multiday_discount_percent`, `campaign_discount_percent`, public_status |
| `venue_users` | venue_id, user_id, role (`owner`/`staff`), status |
| `space_types` | venue_id, name, slug, translations, size_sqm, `breakout_rooms`, `booths`, floor_range, `max_candidates`, `max_recruiters`, `layout_config` (json seating layouts), `ambience`, `catering_included`, `tech_crew_available`, `tech_crew_price`, base_price, amenities, is_active |
| `space_images` | space_type_id, image_url, tags, alt_text, sort_order |
| `space_inventory` | space_type_id, `event_date`, `total_spaces`, `booked_spaces`, price (unique per type and day) |
| `venue_knowledge_items` | venue_id, category, title, body, translations, tags, image_url |
| `conversations` / `conversation_messages` | guest_token, locale, status, current_scene, selected space/facility, reservation_state; role, content, ui_payload |
| `bookings` | reference, venue_id, space_type_id, guest_name, `company_name`, `event_type`, contact, `start_date`, `end_date`, `candidates`, `recruiters`, `space_count`, `tech_crew`, total_price, status |
| `handover_requests` | conversation_id, reason, summary, status, assigned_to |
