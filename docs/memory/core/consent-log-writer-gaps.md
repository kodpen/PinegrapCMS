---
name: consent-log-writer-gaps
description: What the e-mail consent log writers leave unrecorded or record unasked (custom layouts without the box), what was run against them, open questions for the owner (PR #1581)
---

## What writes no row?
- Question not shown: social sign-up (0), contacts created with DEFAULT 1 (forms, profile, `pg_contact_quick_save()`, affiliate), retrieve-order resets, a checkout that hid the box, a widget without the control. POS sales are staff writes.
- A checkout while staff are ghosting: the order's contact (or a new one) takes the staff member's answer, and the log adds no row, so it can differ from `contacts.opt_in`. Nothing in the tree sets `$_SESSION['software']['ghost']` today.
- A signed-in buyer whose card is not updated: the contact keeps its old `opt_in`, so the log does too.

## Which refusals were never asked?
- A custom layout that leaves the box out: registration or membership entrance (counted as asked for every post without `pg_widget_id`), billing information or express order (`opt_in_displayed` is printed by the server, not the layout). Its post looks like an unticked box, so 0 is stored and a `refused` row written. The system layouts and the shipped custom layouts draw the box. The same for a designed custom form widget that no longer draws its `opt_in` field (the field still saves, empty).
- Telling them apart needs the rendered layout, which the post does not carry. Step 5 must not take such rows as answers.

## Was it run?
- Tests: `tests/privacy_test.php`, `member_opt_in_test.php`, `express_order_opt_in_test.php`, `checkout_opt_in_consent_test.php`.
- Sandbox (MariaDB, legacy screens, hand-built widget posts): the sign-up, preferences, one-click and custom form legacy paths, repeated answers and their no-row paths passed.
- One-click (sandbox): the refusal carries the account and its contact, or the contact with user 0; a repeated POST adds none; a later unticked address change, signed in or at membership activation, adds none; re-ticking adds one `granted` `account` row.
- Not run: the checkout writer (tests only), social sign-up, rendered designed widgets, gateway returns, order_history_retrieve_order.php, profile, quick save, affiliate, POS, double opt-in. The legacy preferences screen's first save writes a refused row per open list shown.

## Open for the owner?
- Boxes start ticked (registration, membership, widgets; checkout from `orders.opt_in` DEFAULT 1): untick?
- A designed express order without the box sets `opt_in = 0` on each order and on each contact it updates; social sign-up writes 0 unasked.
- A signed-in buyer whose card is not updated (every member on a designed express order without an `update_contact` control, the default tree included) keeps the old `contacts.opt_in`, so a refusal there is neither stored nor logged: write an asked answer to the contact anyway?
- DEFAULT-1 contacts have no row: step 5 must not push them to İYS as consent.

Related: docs/memory/core/consent-log-writers.md, docs/memory/plans/sms-and-iys.md
