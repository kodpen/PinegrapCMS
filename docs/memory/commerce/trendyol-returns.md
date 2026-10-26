---
name: trendyol-returns
description: Trendyol returns (claims): read on a button for the last 14 days, kept in the session only, approve a return's waiting units after a confirm; the calls, what the docs did not prove
---

## Where are returns shown?
- Account screen (`marketplace_settings.php`), card "Returns from Trendyol" (`#mp-claims`), drawn when the connector can `claims`. Nothing reads returns on a schedule: the "Read returns" button (action `claims_read`) reads, and the approve button (action `claim_approve`) shows only where the connector can `approve_claim`.
- The read is kept in `$_SESSION['software']['marketplace_read'][<account id>]['claims']` = `{read, claims, more}` (`mp_settings_claims_read()`), never in a table: the marketplace holds the returns, a copy would go stale. A failed read keeps the last good one.

## Which call reads them? (`mp_trendyol_claims()`)
- `GET /integration/order/sellers/{sellerId}/claims?startDate=&endDate=&page=&size=50`, every status (no `claimItemStatus` filter). Window: the order feed's, now − 14 days + 1 hour to now, in ms. At most 4 pages of 50 (`mp_trendyol_claim_pages()`, `mp_trendyol_claim_page_size()`; their ceiling is 200); stops at `totalPages`, a short page, or the cap, then `more` is true and the card says only 200 are shown.
- A failed page fails the whole read: a list missing later pages would look complete.
- Docs: developers.trendyol.com/reference/getclaims, modified 2026-01-27, read 2026-10-26.

## What is kept of a return? (`mp_trendyol_claim_rows()`)
- `{claim_id, order_number, package_id, claimed, customer, carrier, tracking, units[{id, barcode, product, price, status, waiting, reason, note}]}`. `claimId` else `id`; a unit is one piece (`claimItems[]`), its reason the customer's, else Trendyol's.
- `cargoTrackingNumber` overflows int64 in their own example (json_decode makes a float), so `tracking` is `cargoSenderNumber` (text).
- Ids are UUID text: `mp_trendyol_claim_id_fits()` allows letters, digits, inner dashes, 1–64. A return without a usable id or unit is left out.
- `waiting` = status `WaitingInAction`; `mp_claim_waiting()` (base.php, provider-neutral) lists those ids.

## How is a return approved? (`mp_trendyol_approve_claim()`)
- `PUT /integration/order/sellers/{sellerId}/claims/{claimId}/items/approve`, body `{"claimLineItemIdList":[...]}`, optional `params` left out. 200 with no body: any 2xx is success. Docs: reference/approveclaimlineitems, modified 2026-01-27.
- The units sent are the waiting units of the return in the session read, never ids posted by the form. A JS confirm names the order and unit count (the buyer is refunded).
- Success: `approved` timestamp on that return in the session ("Approval sent" pill, no button), an activity-log line. Statuses are not changed locally: the next read shows what Trendyol made of it. A refusal is a screen warning.

## What is not built?
- Rejecting (claim issue with reason codes from `/integration/order/claim-issue-reasons`, often with a document); done in the seller panel. Linking a return to the imported order.

## What is unverified?
- No live call. Every claims field beyond the docs' example; whether `orderShipmentPackageId` / `orderOutboundPackageId` is the imported package id; whether only `WaitingInAction` units can be approved (the docs do not say; assumed); the approval's answer body; whether the 14-day window is their limit for this call (none stated).

Related: docs/memory/commerce/trendyol-orders.md, docs/memory/commerce/trendyol-api-facts.md
