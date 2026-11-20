---
name: event-tickets-steps
description: Plan event-tickets, steps TK1 to TK6: tickets made and voided, the holder's page, the check-in screen, places given back and the waiting list, the API and the product's mark, close-out
---

## Which steps are there?
- TK1 (M) Tickets made and voided, server and data only.
  - A migration step adds the tickets table and the product's mark, listed in `get_tables()`; the draft PR's body carries the columns for the architect.
  - Existing files: `includes/fn/order_completion.php` (one call per completed line beside the gift cards' call; one beside `pg_order_folder_access_cancelled()`), `includes/fn/ecommerce.php` (`pg_order_restore_cancelled()`), `includes/settings/features.php` and `features.save.php` (the switch). `includes/fn/ecommerce.php` is held by other open PRs: the step names the clash.
  - New: `includes/fn/event_tickets.php` (which lines give tickets, the code and its hash, make, void and restore, the mail through the queue), `tests/event_tickets_test.php`. Never open beside RF1 of `plans/referral-programme.md`: both enter the cart module and the completion file.
  - Tests, red first: one ticket per unit and the cap; the code not the order's number and stored only hashed; cancel and restore; nothing with the switch off.
  - Features: T1, T2, T3, T4, T10.

- TK2 (M) after TK1: the holder's page. Done in #10583.
  - Existing files: `includes/fn/widgets_account.php` (`_pg_my_account_extras()`), the account lists of `assets/js/style_designer.src.js` (held by other open PRs).
  - New: the root file `tickets.php` behind a signed link.
  - A QR is drawn only when the sha256 of the code drawn again equals the row's `code_hash` (architect, 2026-11-20, `workflow/decisions-made-schema-29.md`).
  - Features: T5, T6.

- TK3 (M) after TK1: the check-in screen.
  - New: the root screen `event_check_in.php` and its script (the browser's barcode reader, the typed code).
  - Tests: the one UPDATE under two scans at once; each answer of T8; an event's ticket refused without the calendar's right and a product's without the commerce right.
  - Pictures on a phone: the pick, a valid scan, a second scan, a wrong event, the name search. To the owner.
  - Features: T7, T8, T9.

- TK4 (M) after TK1: places given back and the waiting list. Done in PR #10578.
  - A migration step adds two columns and an index to `stock_notify` (the event and its date); the draft PR's body carries them for the architect.
  - Existing files: `includes/fn/stock_notify.php` (a request for an event's date, the send when places free), `reserve_calendar_event.php` (the "Tell me" line), `includes/fn/ecommerce.php` (the cancel gives places back). `includes/fn/stock_notify.php` is also entered by VP5 of `plans/visitor-push.md`: never open together.
  - Tests: one request per address and date; a product's stock notice not mailing an event's requests, nor the reverse; places back on a cancel only with tickets on; two frees at once mailing a request once.
  - Features: T11 to T13, T15.

- TK5 (S) after TK3: the API, the event and the product's mark on the product screen. `php tools/check_api_schema.php`. Features: T14, the screen side of T1.
  - The personal data export lists a person's tickets with their order: event, date, state and check-in time, never the code or its hash (architect, 2026-11-20, `workflow/decisions-made-schema-29.md`).

- TK6 (S) close-out: every T number checked against the code with its PR; `commerce/event-tickets.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps run side by side?
- TK2 and TK3 run beside each other once TK1 has landed; TK4 after TK1, in the cart module's order (`plans/more-features-shared-files.md`).
