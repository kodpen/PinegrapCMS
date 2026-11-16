---
name: visitor-push-steps
description: Plan visitor-push, steps VP1 to VP8: the tables and the door, the sender, the block and switch, the panel's card and screen, stock, an order, app mode, close-out
---

## Which steps are there?
- VP1 (M) The tables and the visitor's door.
  - A migration step adds the subscriptions, messages and queue tables, listed in `get_tables()`; the draft PR's body carries their columns and indexes for the architect.
  - Existing files: `includes/fn/privacy.php` (`pg_consent_log_purposes()`, the export and erasure lists), `functions.php` (the module).
  - New: `includes/fn/site_push.php` (ready check, the switch, the check of a subscription, save and remove, the waiting message of a secret), the root file `site_push.php` on the pattern of `consent_answer.php`, `tests/site_push_test.php`.
  - Tests, red first: an endpoint on an unknown host or without https refused, key lengths, the door's origin and rate checks, a secret reading only its own message, nothing written with the switch off.
  - Features: N1 (the door), N4 to N9, N18.
  - Done: #9947 (`platform/visitor-push-rules.md`); no `contact_id` column (a `contact_id` column must be a holder in `includes/fn/contacts.php`; the contact is reached through `user_id`). Waits on the architect's yes to the columns (agent channel message 6520).

- VP2 (M) after VP1: the sender.
  - Existing files: `job.php` and `push_job.php` (the queue's pass).
  - New: the message and queue writers and the send through `pg_push_vapid_token()` and the panel's sender, in the module.
  - Tests: batches, a gone subscription removed, a held service, a message dropped after a day, counts.
  - Features: N10, N11, N12 (the server's side).

- VP3 (M) after VP1: the block and the switch.
  - Existing files: `get_page_content.php` (the script for a page whose markup carries `data-pg-push`), `assets/js/style_designer.src.js` (one row in `_SD_PAGE_SCRIPTS`), `includes/settings/features.php` and `features.save.php` (the card).
  - New: the worker file, `assets/js/pg_site_push.js`, the block family's file with its test.
  - Pictures: the block's three states, a browser that cannot subscribe showing nothing, the notification itself, a phone. To the owner; not marked ready before his look.
  - Features: N1 to N3, N12 (the worker's side).

- VP4 (M) after VP2: the panel.
  - Existing files: `includes/fn/messages_area.php` (the card in `pg_messages_channel_cards()`, `ui/messages-area-rules.md`).
  - New: the screen "Send a notification" and its list. Pictures to the owner.
  - Features: N13, N14.

- VP5 (S) after VP2: stock. Existing files: `includes/fn/stock_notify.php` (the push branch of `pg_stock_notify_send()`, `commerce/back-in-stock-sms.md`), the product page's request. Features: N15.

- VP6 (S) after VP2: an order. Existing files: `includes/fn/events.php` (one listener in `pg_event_record()`), the order's own pages. Features: N16.

- VP7 (S) after VP3: app mode. Existing files: `get_site_icon.php` (the manifest's `display`), the worker file (the offline page). Features: N17.

- VP8 (S) close-out: every N number checked against the code with its PR; `platform/visitor-push.md`; this plan's three files deleted and its line in `plans/website-features.md` removed.

## Which steps run side by side?
- VP2 and VP3 run beside each other once VP1 has landed; VP5 and VP6 beside VP4.
