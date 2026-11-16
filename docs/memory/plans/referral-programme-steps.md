---
name: referral-programme-steps
description: Plan referral-programme, steps RF1 to RF5: the census of commission readers and the rules on the order path, the settings card and the mail, My Account's part, the report and the event, close-out
---

## Which steps are there?
- RF1 (M, after the owner's yes on the set's schema) The rules, server and data only.
  - First a census in the draft PR's body: every reader of `commissions` (screens, lists, reports, the API, the affiliate's account part, the personal data export and erasure) and what each does with a referral's row. The architect confirms it there before the rest is written.
  - A migration step adds the value `referral` to `gift_cards.kind`.
  - Existing files, one call each beside what affiliates and points already have there: `includes/fn/order_completion.php` (at the commission's call), `includes/fn/ecommerce.php` (`process_order_cancellation()`, `pg_order_restore_cancelled()`), `includes/fn/returns.php` and the order screen's line refund, `includes/fn/store_credit.php` (the kind), `includes/fn/commission_list.php` (a referral's row not set by hand).
  - New: `includes/fn/referral.php` (the code made and checked, who can refer, the checks of E7 as one pure function, the reward, the take-back), `tests/referral_test.php`.
  - Tests, red first: every refusal of E7; the month's room under two orders at once; cancel, restore and refund; spent credit noted; an affiliate's order still making a commission and no reward; an affiliate's code winning over a referral code; nothing with the card off.
  - Features: E2 to E9, E11, E14 (the rule), E15.

- RF2 (S) after RF1: the settings card and the mail.
  - Existing files: `includes/settings/registry.php` (the card's row).
  - New: the card and its save, kept as settings texts; the mail's text with its Turkish.
  - Features: E1, E3, E10.

- RF3 (M) after RF1: My Account's part.
  - Existing files: `includes/fn/widgets_account.php` (`_pg_my_account_extras()`), the two account lists of `assets/js/style_designer.src.js` (held by other open PRs: the step names the clash).
  - Pictures: the part with no referral yet, with some in each state, a customer who cannot refer yet, a phone. To the owner; not marked ready before his look.
  - Features: E12.

- RF4 (S) after RF1: the report and the event.
  - Existing files: `includes/fn/output.php` (one menu link), `includes/api/outbound/webhooks.php` (the event's name).
  - New: the report screen. Pictures to the owner.
  - Features: E13, E14 (the event).

- RF5 (S) close-out: every E number checked against the code with its PR; `commerce/referral-programme.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps run side by side?
- Until the owner's yes on the set's schema each waits for the one before it, so none is free. After it: RF2, RF3 and RF4 run beside each other once RF1 has landed. RF1 is never open beside TK1 of `plans/event-tickets.md`: both enter the cart module and the completion file (`plans/more-features-shared-files.md`).
