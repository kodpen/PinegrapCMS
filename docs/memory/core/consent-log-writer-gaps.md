---
name: consent-log-writer-gaps
description: What the e-mail consent log writers leave unrecorded, what was run against them, and the questions left for the owner (PR #1581)
---

## What writes no row?
- Question not shown: social sign-up (0), contacts created with DEFAULT 1 (forms, profile, `pg_contact_quick_save()`, affiliate), retrieve-order resets, a checkout that hid the box, a widget without the control. POS sales are staff writes.
- A checkout while staff are ghosting, and a signed-in buyer whose card is not updated: the contact keeps its old `opt_in`, so the log does too.

## Was it run?
- Tests: `tests/privacy_test.php`, `member_opt_in_test.php`, `express_order_opt_in_test.php`, `checkout_opt_in_consent_test.php`.
- Sandbox (MariaDB, legacy screens, hand-built widget posts): the sign-up, preferences, one-click and custom form legacy paths, repeated answers and their no-row paths passed.
- One-click (sandbox): the refusal carries the account and its contact, or the contact with user 0; a repeated POST adds none; a later unticked address change, signed in or at membership activation, adds none; re-ticking adds one `granted` `account` row.
- Not run: the checkout writer (tests only), social sign-up, rendered designed widgets, gateway returns, order_history_retrieve_order.php, profile, quick save, affiliate, POS, double opt-in. The legacy preferences screen's first save writes a refused row per open list shown.

## Open for the owner?
- Boxes start ticked (registration, membership, widgets; checkout from `orders.opt_in` DEFAULT 1): untick?
- A designed express order without the box writes `opt_in = 0` for every buyer; social sign-up writes 0 unasked.
- A signed-in buyer whose card is not updated (every member on a designed express order, which has no `update_contact` box) keeps the old `contacts.opt_in`, so a refusal there is neither stored nor logged: write an asked answer to the contact anyway?
- DEFAULT-1 contacts have no row: step 5 must not push them to İYS as consent.

Related: docs/memory/core/consent-log-writers.md, docs/memory/plans/sms-and-iys.md
