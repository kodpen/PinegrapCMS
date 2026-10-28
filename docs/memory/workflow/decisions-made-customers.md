---
name: decisions-made-customers
description: Decisions list, customer data part (owner, 2026-10-28): marketing boxes stay ticked, İYS never narrows sending beyond refusals, review order and photos, merge re-points all but consent_log
---

## How is marketing consent collected?
- The marketing boxes stay ticked as they are today (registration, membership, the widgets, checkout), and an express order without the box or a social sign-up keeps storing `opt_in` as today (#2431). Owner, 2026-10-28: visitor tracking and marketing are features of the software; KVKK impositions are not a priority.
- With İYS on, a commercial e-mail goes to every opted-in address İYS has not refused, as today; İYS never narrows sending further, and the client stays optional (#2430). Owner, 2026-10-28: a site owner who is not a company cannot even register with İYS, so an approved-only rule would stop every such site from mailing.

## How are product reviews shown?
- Product reviews are listed in an objective order: newest first, verified purchases only, no featured-first (28/B(4)); a store reply shows under its review. Featured-first stays for other comment threads (#2426).
- Photo reviews come by a site setting, off by default, images only, shown in the moderation queue and in the product's review list (#2426).

## What does merging two contacts re-point?
- `merge_contacts()` re-points every `contact_id` holder by one shared list (orders, forms, `email_recipients`, `ws_channels`, `ws_refs`, cart quotes, the ERP account through `erp_account_link_contact()`, refused when both contacts have one), writes an audit line, and leaves `consent_log` rows as they are: consent proof is per address and must name who gave it. The list is shared with erasure, so a new holder is added once (#2421).

## Who decided these?
- The owner (2026-10-28) for consent and İYS; the architect (2026-10-27) for reviews and the merge, confirmed by the owner. Reasons are on the issues.
