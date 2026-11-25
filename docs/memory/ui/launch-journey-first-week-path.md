---
name: launch-journey-first-week-path
description: After the set-up (SJ4): the First week card, a new team member's Start here card, the hint lines under catalogue screens and their hint:<screen> keys in user.tours_seen
module: launch_journey_path
---

## Where are they?
- Pure parts in `includes/fn/launch_journey_path.php` (tests `tests/launch_journey_path_test.php`); card data `includes/dashboard/v2/cards/firstweek.php` and `starthere.php`; both renderers in `assets/js/pg_dash_cards_launch.js`. New dashboard only.
- `pg_dashboard_v2_launch_first()` puts launch (L), firstweek (M) and starthere (M) first in a stored layout that lacks them, while their gate is open.

## When is the First week card drawn?
- Gate `firstweek`: role 0 and `pg_launch_journey_first_week()` open, i.e. the seven days from the stored `live_at` ("Go live", `ui/launch-journey-go-live.md`). It ends by itself.
- Head "Day N of 7"; four rows with a pill Waiting, Seen or Problem (`pg_launch_journey_week_card()`): first order or form entry (orders only with the shop on), visitors, first e-mail delivered, alerts.
- Sources since `live_at`: `pg_dashboard_v2_chip_count()` forms, visits, orders; `mail_outbox` sent (`sent_at`) and failed (`created_at`); `pg_ops_alert_state()['sent']` times. Mail only failed is a Problem; any alert is a Problem; a source not read says "Could not be checked".
- A mail sent at once without scheduled tasks leaves no queue row, so that row keeps waiting.

## Who gets Start here, and what rows?
- Gate `starthere`: role 1 to 3, account new (`user.user_created` within 30 days, `PG_LAUNCH_JOURNEY_NEWCOMER`; 0 is never new, so accounts older than the column get nothing) and `start_here.1` not in `tours_seen`.
- `pg_launch_journey_path($role, $may)`: Designer pages, page styles, menus, files, comments; Manager orders, pages, form entries, contacts, files; User pages, orders, form entries, contacts, calendars, files. Only screens `pg_dashboard_v2_starthere_may()` allows (each screen's own access rule), five at most. Role 0 gets none.
- "Hide" on the card menu posts panel action `tour_seen` with `start_here.1`; the gate then closes.

## How are hint lines drawn and marked?
- `pg_page_shell()` appends `pg_launch_journey_hint_html()` after `output_header()` (not with `hide_menu`): `.alert.theme-info.small`, one line, one quiet "Don't show again".
- Only for screens in `pg_launch_journey_screens()` (the path's screens plus connections and add_user), only for a new account (same 30 days), until marked.
- "Don't show again" posts `tour_seen` with `hint:<screen>`. `pg_tour_mark()` takes a key only through `pg_tour_key_allowed()`: a tour key with its version, or `hint:` plus a catalogue screen (`workflow/decisions-made-schema-46.md`); `pg_tour_list_marked()` keeps a hint key once.
