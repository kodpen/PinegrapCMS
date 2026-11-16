---
name: referral-programme-steps
description: Plan referral-programme, steps RF1 to RF5: the table and the rules on the order path, the settings card and the mail, My Account's part, the report and the event, close-out
---

## Which steps are there?
- RF1 (M) The rules, server and data only. After the owner's yes on tables.
  - A migration step adds one table of referrals and the credit's new kind, listed in `get_tables()`; the draft PR's body carries the columns for the architect.
  - Existing files, one call each beside what affiliates and points already have there: `includes/fn/order_completion.php` (at the commission's call), `includes/fn/ecommerce.php` (`process_order_cancellation()`, `pg_order_restore_cancelled()`), `includes/fn/returns.php` and the order screen's line refund (where points are taken back), `includes/fn/contacts.php` and `includes/fn/privacy.php` (the two people of a row). The cart module is held by other open PRs: the step names the clash.
  - New: `includes/fn/referral.php` (the code made and checked, who can refer, the checks of E7 as one pure function, the reward, the take-back), `tests/referral_test.php`.
  - Tests, red first: every refusal of E7; the month's room under two orders at once; cancel, restore and refund; spent credit noted; an affiliate's order still making a commission and no reward; nothing with the card off.
  - Features: E2 to E8, E10, E14, E15.

- RF2 (S) after RF1: the settings card and the mail.
  - Existing files: `includes/settings/registry.php` (the card's row).
  - New: the card and its save, kept as settings texts; the mail's text with its Turkish.
  - Features: E1, E3, E9.

- RF3 (M) after RF1: My Account's part.
  - Existing files: `includes/fn/widgets_account.php` (`_pg_my_account_extras()`), the two account lists of `assets/js/style_designer.src.js` (held by other open PRs: the step names the clash).
  - Pictures: the part with no referral yet, with some in each state, a customer who cannot refer yet, a phone. To the owner; not marked ready before his look.
  - Features: E11.

- RF4 (S) after RF1: the report and the event.
  - Existing files: `includes/fn/output.php` (one menu link), `includes/api/outbound/webhooks.php` (the event's name).
  - New: the report screen. Pictures to the owner.
  - Features: E12, E13.

- RF5 (S) close-out: every E number checked against the code with its PR; `commerce/referral-programme.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps run side by side?
- RF2, RF3 and RF4 run beside each other once RF1 has landed.
