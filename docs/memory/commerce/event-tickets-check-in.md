---
name: event-tickets-check-in
description: Event ticket check-in at the door (TK3): event_check_in.php, the pick, the camera through BarcodeDetector or the typed code, one conditional UPDATE, the answers, rights, name search, counts, undo
module: event_check_in
---

## Where is it?
- Root screen `event_check_in.php`, functions in `includes/fn/event_check_in.php` (loaded by the screen only), script `assets/js/event_check_in.js` (served as written, no twin, no bundle). Every action is a POST to the same file answered in JSON (`request` = counts, search, scan, check_in, undo), with the session token compared by `hash_equals()`.
- No menu entry yet: the address is `event_check_in.php`, `?pick=` keeps the choice. Help opens `calendars#ticket-check-in` (`get_help_url.php`), a section still to write.

## Who may use it?
- No new right (architect, message 6554). An event's ticket: role < 3, or `manage_calendars` and `validate_calendar_event_access()` on its event. A product's ticket: role < 3 or `manage_ecommerce` (`pg_check_in_allowed()`). The pick list holds only what the user may check in.
- Undo a check-in: role 2 (Manager) or above (`pg_check_in_may_undo()`); a role-3 user with the rights checks in but does not undo.

## What is picked?
- `e-<event>-<recurrence_number>` for an event's date, `p-<product>` for a product's tickets (rows with no event). Listed from the tickets sold (void left out); 500 dates at most, those sold last first (`pg_check_in_picks_groups_sql()`), then an event that ended over a day ago drops off. Dates via `get_absolute_time()`, so day and month names are translated.

## How is a ticket checked in?
- The code (camera or typed) is hashed (`pg_event_ticket_code_hash()`) and looked up by `code_hash`; the door needs no key. The script takes the first twelve code letters in the QR text, so a QR holding the code alone or inside a link both work.
- A valid ticket: `UPDATE event_tickets SET state = 'used', used_at, used_by WHERE id = ? AND state = 'valid'`. Of two phones scanning at once only one changes the row; the other reads it again and answers what the row then is (`pg_check_in_run()`): used, void (cancelled meanwhile), unknown (deleted meanwhile), or failed when the write itself failed (#10754).
- Undo: `SET state = 'valid', used_at = 0, used_by = 0 WHERE id = ? AND state = 'used'`, so a void ticket stays void.

## Which answers are there?
- In order: unknown code, not allowed (no right on the ticket; nothing of it shown), void, another event or date (names the event, or the product by its short description else its name, `pg_check_in_product_label()`), used before (when and by whom), valid ("Valid ticket", the name and "ticket 2 of 4", the order number). `pg_check_in_answer()`, words in `pg_check_in_answer_text()`.
- A seventh, "failed" ("The ticket could not be checked in", scan it again), only after a write that failed; it is never announced (#10754).
- The guest's name: the one the tickets page shows, `pg_event_ticket_guest_name()` on the unit's product form (`form_data.quantity_number` = the ticket's unit; `pg_check_in_names_from_forms()`), else the buyer's billing name.

## What else does the screen do?
- Counts "N of M checked in" for the pick: used against valid + used.
- Name search (2 characters or more): buyer name, the unit's form names (contact name fields, or a field named by `PG_EVENT_TICKET_GUEST_FIELD_PATTERN` through `REGEXP`) or the exact order number, 30 rows, void left out; each row checks in by ticket id (`pg_check_in_search_sql()`).
- A code typed during a camera scan's answer is not sent and stays in the field.
- The camera: `BarcodeDetector` with `qr_code`, rear camera, a frame every 250 ms, one code read once per 4 s, stopped when the tab is hidden; without the reader (Firefox, Chromium on Linux) the screen says to type the code. No library.

Test: `tests/event_check_in_test.php` (two scans at once, each answer, the rights, the pick, names, counts, undo). The camera path is not reached by a unit test.

Related: docs/memory/commerce/event-tickets-model.md, docs/memory/commerce/event-tickets.md
