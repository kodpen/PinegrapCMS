---
name: lead-journey-view
description: The visitor journey read and drawn (LJ2, #10946): the merge by time, the contact screen's Journey card, the Workspace card's folded row, erase, export, inventory, the keep period trim
module: lead_journey_view
---

## What is read? (`pg_lead_journey_read()`)
- Nothing unless the contact has a `lead_visits` row: then the contact screen shows one grey line ("No visits are tied to this contact: …") and the Workspace card nothing.
- Rows, newest first (`pg_lead_journey_merge()`; same second by the order of `pg_lead_journey_kinds()`): visits (pages and minutes from `pages` JSON) and `contact_journey` rows that stand for an act (`pg_lead_journey_row_kind()`: kind 1 tie with detail `newsletter` or `campaign_click`, kind 2 download; a form, order or sign-in tie is no row, its record is).
- Then complete forms by `contact_id`, orders `complete`/`exported` by `contact_id`, bookings (not held) and course steps (lesson done, course complete) of the contact's users.
- At most 200 rows a source. Orders only with the store right, forms with the forms right and in a folder the reader may open (`check_edit_access()`; Workspace: `ws_viewer_folder_open()`).
- "First came from": the earliest visit's channel (as the visitor reports name it), campaign, landing page.
- A contact whose user has panel work (`waf_user_id_is_staff()`) shows no journey (J14).

## How is it drawn?
- Contact screen (`edit_contact.php`, under the cards, `pg_lead_journey_contact_card()`): card "Journey", the first-came-from line, segments All / Visits / Forms and bookings / Orders, a day heading a group, rows as icon tile + sentence + time; a visit's "Pages" toggle opens its pages. 20 rows, then "Show earlier" adds 20. Filters, toggles and paging in `assets/js/lead_journey.js` (plain script, all rows printed by the server).
- Workspace customer card: `card.journey` from `pg_lead_journey_card()` (contact open to the reader only), drawn by `journeyPart()` in `workspace_customer.js` as a folded "Journey" under History: first-came-from and the newest 5 rows, "Open the contact" quiet when the reader may open contacts.

## What do erase, export and the inventory do?
- Erase (`pg_privacy_erase_tables()`, after `course_progress`): `lead_visits`, `contact_journey`, `contact_scores`, `lead_browsers` deleted by `contact_ids`; `pg_contact_holders()` erase `find` for the four. A visit no contact holds stays until the keep period.
- Export sections `journey_visits`, `journey`, `interest_score` (after `courses`).
- Inventory: the four tables, category `ip_cookie`, ground consent, retention `lead_journey` ("The journey's keep period (13 months)").

## How is the keep period kept?
- `job.php` calls `pg_lead_trim_run(time())` every tick: deletes at most 2,000 rows a table of `lead_visits` (`started_at`), `contact_journey` (`at`) and `lead_browsers` (`last_seen_at`) before `pg_lead_keep_start()` (13 months; the setting comes with LJ3). `contact_scores` is not trimmed.

Tests: `tests/lead_journey_view_test.php`.
