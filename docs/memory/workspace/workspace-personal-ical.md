---
name: workspace-personal-ical
description: A person's own secret iCal address as a read-only layer on their Work Calendar: ws_holiday_feeds.user_id, the sealed payload, the sync path, who sees it (#3254, decided in #3180)
---

## What does it do?
- A person pastes the secret iCal address of their Google, Outlook or Apple calendar on Calendar subscription (`workspace_calendar_feed.php`, card `#ws-own-calendars`). Its events show on their Work Calendar (`ws_calendar()`), read-only, only to them, with Hide/Show, Read now and Remove. Up to `WS_PERSONAL_ICAL_MAX` (5) addresses each.
- Step 11.0.117 of 2026.11.0 adds `ws_holiday_feeds.user_id` (+ `idx_user`); `ws_personal_ical_ready()` probes it.

## Where are the address and the events kept?
- In the person's `ws_holiday_feeds` row (`user_id` > 0). `url` holds one blob sealed with the site's key (`ws_personal_ical_seal_with()`, AES-256-CBC like `encrypt_string_with_iv()`): `{url, on, events}`. The address is never shown again, only its host. No key on the site: the address is refused.
- Events are never written to `ws_events`. Many readers read that table without asking whose event it is (plan items by id, briefing, reminders, refs, the outgoing calendar subscription, `ws_holiday_year()`), so a personal event there would leak or count as a day off. Kept events are trimmed to `WS_PERSONAL_ICAL_PAYLOAD_MAX` bytes (the sealed blob must fit TEXT), nearest first, 62 days back to 366 ahead.

## How is it read?
- The public holiday job does it: `ws_holiday_feeds_tick()` picks any row; `ws_holiday_feed_sync()` hands a row with `user_id` > 0 to `ws_personal_ical_sync()`. The address goes through `api_http_request()` (resolve + private-range check, no redirects); on paste `ws_personal_ical_address()` accepts only https/webcal, no credentials, no localhost/.local and no blocked IP literal.
- `ws_personal_ical_events()` reads timed and all-day events (Z = UTC, TZID, floating = site zone), RRULE DAILY/WEEKLY(+BYDAY)/MONTHLY/YEARLY with INTERVAL, COUNT, UNTIL, EXDATE and RECURRENCE-ID overrides; other rules give the first occurrence; CANCELLED is dropped. Without DTEND the end comes from DURATION (`ws_ical_duration()`: PnW, PnDTnHnMnS). `ws_ical_vevents()` / `ws_ical_text()` are shared with the holiday reader, which still keeps all-day events only.
- A hidden calendar is not called; an account that left the team has its rows deleted on the next read. Sync errors show only on the owner's card (they never name the address).

## Why are nested blocks skipped and lines split outside quotes?
- `ws_ical_vevents()` used to write every line between BEGIN:VEVENT and END:VEVENT into one array, a later name winning. A VALARM's SUMMARY/DESCRIPTION/UID replaced the event's: Google events with an e-mail reminder showed as "Alarm notification", and with Apple's reminder UIDs a moved weekly occurrence showed twice (the `UID|time` match broke). Now any nested BEGIN:…END: block is skipped by depth (#3392).
- A line is split at the first ':' outside double quotes and the name at the first ';' outside them; `params` stays the raw text after the name. A quoted `TZID="(UTC+03:00) Istanbul"` used to drop the event; now an unknown zone falls back to the site's (#3392, PR #3490).

## What stays as before?
- Site-wide rows (`user_id` 0): `ws_holiday_feeds()` and the settings card read only those (`ws_holiday_feed_owner_where(0)`), and the admin's Read now / Remove refuse a person's row (`ws_holiday_feed_site_wide()`).
- The layer is placed by `ws_personal_ical_place()` as events with `id` 0, `kind` other, `layer` (calendar name), `readonly` true, only when the calendar shows the reader's own view (person filter 0 or themselves).

## What is left?
- Writing tasks to the person's calendar and reading changes back (OAuth step, `plans/reverse-oauth-leftovers.md`).
