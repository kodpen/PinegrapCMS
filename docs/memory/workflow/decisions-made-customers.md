---
name: decisions-made-customers
description: Decisions list, customer data (owner, 2026-10-28): marketing boxes stay ticked, İYS never narrows sending, export unchanged, review order and photos, merge re-points all but consent_log
---

## How is marketing consent collected?
- The marketing boxes stay ticked as they are today (registration, membership, the widgets, checkout), and an express order without the box or a social sign-up keeps storing `opt_in` as today (#2431). Owner, 2026-10-28: visitor tracking and marketing are features of the software; KVKK impositions are not a priority.
- With İYS on, a commercial e-mail goes to every opted-in address İYS has not refused, as today; İYS never narrows sending further, and the client stays optional (#2430). Owner, 2026-10-28: a site owner who is not a company cannot even register with İYS, so an approved-only rule would stop every such site from mailing.

## What does the personal data export hold?
- The export pack stays as it is: the site log, `audit_log` and `visitors` rows are not added to it (#2434). Owner, 2026-10-28: KVKK-driven work is not a priority.
- The cookie notice's `pg_consent` cookie carries no id; each anonymous consent row stands alone (#2435).

## How are product reviews shown?
- Product reviews are listed in an objective order: newest first, verified purchases only, no featured-first (28/B(4)); a store reply shows under its review. Featured-first stays for other comment threads (#2426).
- Photo reviews come by a site setting, off by default, images only, shown in the moderation queue and in the product's review list (#2426). Its column (#2818, architect): `config.review_photos` TINYINT(1) DEFAULT 0 by `install_add_column()` in 2026.11.0; on a review thread that setting alone allows an attachment, one image per comment checked with `getimagesize()`; the page's attachment switch is ignored there and keeps ruling every other thread.

## What does merging two contacts re-point?
- `merge_contacts()` re-points every `contact_id` holder by one shared list (orders, forms, `email_recipients`, `ws_channels`, `ws_refs`, cart quotes, the ERP account through `erp_account_link_contact()`, refused when both contacts have one), writes an audit line, and leaves `consent_log` rows as they are: consent proof is per address and must name who gave it. The list is shared with erasure, so a new holder is added once (#2421).

## Who decided these?
- The owner (2026-10-28) for consent, İYS and the export; the architect (2026-10-27) for reviews and the merge, confirmed by the owner. Reasons are on the issues.
