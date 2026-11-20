---
name: bug-hunt-areas-2
description: The areas given to a bug hunt from 2026-11-17 on (wholesale prices, publish approval, visitor push, gift cards and store credit), the issues each ended with and what it read clean
---

## Which areas were hunted from 2026-11-17 on, and what did each find?
- The hunts before: `bug-hunt-areas.md`; from 2026-11-19 on: `bug-hunt-areas-3.md`. The next area is none of the three files.
- Given on 2026-11-17 (channel message 8165), every open bug issue having a PR or a worker: the wholesale prices (the offers' group condition, shared prices, the minimum order, quantity prices on a product page; WS1, WS2, WS4, WS5). It ended the same day with #10195 to #10197 (channel message 8182).
- Read and found clean there: the group condition on every offer path; the one-hop pointer and its refusals; the lower of an account's and a campaign's price, never stacked; retail in feeds, structured data, price history and the API; the page copy marked personal; the minimum's highest group and its refusal at submit.
- Given on 2026-11-17 (channel message 8337): publish approval's server side (PA2 #9892, PA4a #9930, PA4b #9939, PA5 #9968): every save door, who may apply, a page changed since, what a visitor sees meanwhile. Known, not reported: PA3 (#10188) and the close-out. It ended the same day with #10215 to #10220 (channel message 8399).
- Read and found clean there: a held save after the gate writes nothing before it; the API, assistant and import doors untouched as decided; apply, dismiss and take back refuse role 2 in the panel and in the Workspace answer; a stale change, two editors on one element, the switch turned off.
- Four of its fixes (#10216 to #10219) share `includes/fn/content_proposals.php`: `held-file-hunt-fixes.md`.
- Given on 2026-11-17 (channel message 8702): visitor push's server side (VP1 #9947, VP2 #9957, VP4 #9977, VP5 #9975, VP6 #9976; VP3, VP6b and VP7 are not built). It ended the same day with #10247 to #10249 (channel message 8720) and, on the architect's word, #10251 (the sender's pace: `platform/visitor-push-sender.md`).
- Read and found clean there: the endpoint's host check, the secret's scope, the door's order and rate limit, removal on 404/410 and the hold on 429, who may watch an order, a stock push sent once, the panel's role and CSRF, privacy export and erasure.
- Given on 2026-11-17 (channel message 8783): gift cards and store credit, the spending and the giving back (a card applied to a cart, the check at submit, the redemption, what a cancelled or refunded order gives back, the credit and gift card API, the ERP settlement, export and erasure). It ended the same day with #10258 to #10261 (channel message 8797).
- Read and found clean there: the redemption's balance guard, the re-check at submit, the owner check of a typed code, the checkout's lock, a card removed from an order, issued cards on a cancellation and a restore, the refund line, the cancelled order's credit refund and its bonus base, issue, void, reduce and expiry of credit, a deleted user's credit, the customer credit API, the gift card list's quick edit, the ERP settle and unsettle.
- Its fixes are in files of their own. Architect (2026-11-17, channel message 8803): #10261 goes beside the draft #10042 on `includes/fn/privacy.php`, in `_pg_privacy_erase_apply_store_credit()` alone; #10042's hunks are in `pg_privacy_erase_tables()` and `pg_privacy_erase_labels()`. The count #10259 and #10260 share: `commerce/gift-card-part-owed.md`; the owner's answer on #10258: `decisions-made-product-72.md`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas.md, docs/memory/workflow/held-file-hunt-fixes.md
