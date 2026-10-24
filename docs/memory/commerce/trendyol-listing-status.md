---
name: trendyol-listing-status
description: Trendyol listing approval read onto the mapping row: the two filter calls, what each state writes, which rows are asked about and how the set turns, what is not stored, unverified points.
---

## Which calls say where a listing stands?
- `GET /integration/product/sellers/{sellerId}/products/approved?barcodes=<b1,b2,…>&page=0&size=100`: contents, the barcode inside `variants[]` (with `commission`, `onSale`, `locked`/`lockReason`, `archived`, `blacklisted`, `productUrl`); and `…/products/unapproved?barcodes=…&page=0&size=1000`: one entry per barcode, `status` `pendingApproval` or `rejected`, `rejectReasonDetails[]`, `docNeeded`. At most 50 barcodes a call (more refused before it; each `rawurlencode`d, joined by commas); barcodes their rules refuse are left out (`data['refused']`). Docs read 2026-10-24 (pages modified 2026-09-09/11).
- `mp_trendyol_listing_status()` (connector entry `listing_status`); readers `mp_trendyol_approved_states()` / `mp_trendyol_unapproved_states()`, barcodes matched without regard to case. In neither answer → `unknown`.

## What does each state write on the mapping row? (`mp_sync_listing_row_update()`)
- approved: `remote_id` = `contentId`; a row in `error` (its stock line refused while it waited) → `active` and is queued again; `paused` stays paused; `last_error` cleared, or a note when blacklisted, locked (with the reason) or archived (status untouched).
- rejected: `error`, `last_error` = "Rejected by Trendyol: reason: detail; …" (500 max).
- pending: nothing; with `docNeeded` a note that a document is needed, status untouched.
- unknown: nothing; the listing may still be going through their batch.
- Only changed columns are written. A later successful stock push clears a note (`last_error = ''`).

## Which rows are asked about? (`mp_sync_listing_states()`)
- After the poll step of each pass, per active account whose connector has `listing_status`: rows with a `remote_code`, `remote_id` empty, and not `error` with the rejected prefix (`mp_listing_rejected_prefix()`, read from the translated text so it matches what was written).
- At most 100 a pass, 50 a call (4 calls); a window that moves on by 100 each 5-minute pass (`mp_sync_listing_window()`), so rows Trendyol never answers for (deleted, never listed, a failed batch) cannot hide the others. Approved and rejected rows leave the set. A failed call writes the account's error and stops that account for the pass; success does not clear the account's error (it would hide a push failure).
- A rejected row comes back into the set when the article is listed again (its batch success sets it `active`) or the product is matched again.

## What does the screen show?
- Matched products, for a connector with `listing_status`: "On Trendyol" once `remote_id` is set, "Rejected" with the reason, "Awaiting approval" for an active row without it; a note on a non-error row is shown under the code.

## What is not stored?
- `commission`, `productUrl`, `onSale`: no column (no schema change). Commission shown read-only needs one (plan step 2b-ii, owner question).

## What is unverified?
- No live call; fixtures follow the doc samples. Whether `barcodes` takes a comma list; whether a rejected listing re-enters approval on a new create or needs the update call.

Related: docs/memory/commerce/trendyol-listings.md, docs/memory/commerce/trendyol-stock-price.md
