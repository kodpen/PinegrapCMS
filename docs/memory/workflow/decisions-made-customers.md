---
name: decisions-made-customers
description: Decisions list, customer data part (2026-10-27): opt-in unticked, never without a box; İYS approved-only sending after the first sync; review order and photos; merge re-points all but consent_log
---

## How is marketing consent collected?
- The marketing boxes start unticked everywhere (registration, membership, the widgets, checkout), and `opt_in` is written from the box only; the column default is not touched and existing contacts keep what they have (#2431). An express order without the box and a social sign-up store `opt_in = 0`: no box, no consent. Why: KVKK/GDPR and İYS count a pre-ticked consent as none, and a consent İYS does not hold cannot be pushed.
- With İYS on, a commercial e-mail goes only to addresses İYS shows as approved, from the moment the site's first İYS push and pull have completed; until then today's rule (every opted-in address İYS has not refused), so switching İYS on is not a blackout. The campaign screen shows how many addresses are left out and why (#2430).

## How are product reviews shown?
- Product reviews are listed in an objective order: newest first, verified purchases only, no featured-first (28/B(4)); a store reply shows under its review. Featured-first stays for other comment threads (#2426).
- Photo reviews come by a site setting, off by default, images only, shown in the moderation queue and in the product's review list (#2426).

## What does merging two contacts re-point?
- `merge_contacts()` re-points every `contact_id` holder by one shared list (orders, forms, `email_recipients`, `ws_channels`, `ws_refs`, cart quotes, the ERP account through `erp_account_link_contact()`, refused when both contacts have one), writes an audit line, and leaves `consent_log` rows as they are: consent proof is per address and must name who gave it. The list is shared with erasure, so a new holder is added once (#2421).

## Who decided these?
- The architect, under the owner's rule of 2026-10-27 that only big architectural changes are asked (`workflow/architect.md`); the reasons are on the issues.
