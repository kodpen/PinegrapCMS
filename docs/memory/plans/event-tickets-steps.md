---
name: event-tickets-steps
description: Plan event-tickets, steps TK1 to TK6: tickets made and voided, the holder's page, the check-in screen, places given back and the waiting list, the API and the product's mark, close-out
---

## Which steps are there?
- TK1 (M) Tickets made and voided, server and data only. After the owner's yes on tables.
  - A migration step adds the tickets table and the product's mark, listed in `get_tables()`; the draft PR's body carries the columns for the architect.
  - Existing files: `includes/fn/order_completion.php` (one call per completed line beside the gift cards' call; one beside `pg_order_folder_access_cancelled()`), `includes/fn/ecommerce.php` (`pg_order_restore_cancelled()`), `includes/settings/features.php` and `features.save.php` (the switch). `includes/fn/ecommerce.php` is held by other open PRs: the step names the clash.
  - New: `includes/fn/event_tickets.php` (which lines give tickets, the code and its hash, make, void and restore, the mail through the queue), `tests/event_tickets_test.php`.
  - Tests, red first: one ticket per unit and the cap; the code not the order's number and stored only hashed; cancel and restore; nothing with the switch off.
  - Features: T1, T2, T3, T4, T10.

- TK2 (M) after TK1: the holder's page.
  - Existing files: `includes/fn/widgets_account.php` (`_pg_my_account_extras()`), the account lists of `assets/js/style_designer.src.js` (held by other open PRs).
  - New: the root file `tickets.php` behind a signed link.
  - Pictures: one ticket, four tickets, a void one, a phone, the printed page. To the owner; not marked ready before his look.
  - Features: T5, T6.

- TK3 (M) after TK1: the check-in screen.
  - New: the root screen `event_check_in.php` and its script (the browser's barcode reader, the typed code).
  - Tests: the one UPDATE under two scans at once; each answer of T8; a user without the calendar's right refused.
  - Pictures on a phone: the pick, a valid scan, a second scan, a wrong event, the name search. To the owner.
  - Features: T7, T8, T9.

- TK4 (M) after TK1 and the owner's answer on T11: places given back and the waiting list.
  - A migration step adds the waiting list's table (requests and holds); columns to the architect in the draft PR.
  - Existing files: `reserve_calendar_event.php` and `includes/fn/ecommerce.php` (`check_reservations()` counts holds; the cancel gives places back), `job.php` (the pass), `includes/fn/contacts.php` and `includes/fn/privacy.php`.
  - New: `includes/fn/event_waitlist.php`, its test.
  - Tests: a hold claimed once under two requests; an ended hold passed on; the job's two runs mailing once.
  - Features: T11 to T14, T16.

- TK5 (S) after TK3: the API, the event and the product's mark on the product screen. `php tools/check_api_schema.php`. Features: T15, the screen side of T1.

- TK6 (S) close-out: every T number checked against the code with its PR; `commerce/event-tickets.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps run side by side?
- TK2 and TK3 run beside each other once TK1 has landed; TK4 after TK1, alone on `includes/fn/ecommerce.php`.
