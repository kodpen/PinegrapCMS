---
name: trendyol-listing-status
description: Trendyol listing approval read onto the mapping row: the two filter calls, what each state writes, which rows are asked about and how the set turns, what is not stored, unverified points.
---

## Which calls say where a listing stands?
- `GET /integration/product/sellers/{sellerId}/products/approved?barcodes=<b1,b2,…>&page=0&size=100`: contents, the barcode inside `variants[]` (with `commission`, `onSale`, `locked`/`lockReason`, `archived`, `blacklisted`, `productUrl`); and `…/products/unapproved?barcodes=…&page=0&size=1000`: one entry per barcode, `status` `pendingApproval` or `rejected`, `rejectReasonDetails[]`, `docNeeded`. At most 50 barcodes a call (more refused before it; each `rawurlencode`d, joined by commas); barcodes their rules refuse are left out (`data['refused']`). Docs read 2026-10-24.
- `mp_trendyol_listing_status()` (connector entry `listing_status`); readers `mp_trendyol_approved_states()` / `mp_trendyol_unapproved_states()`, barcodes matched without regard to case. In neither answer → `unknown`.

## What does each state write on the mapping row? (`mp_sync_listing_row_update()`)
- approved: `remote_id` = `contentId`; a row in `error` (its stock line refused while it waited) → `active` and is queued again; `paused` stays paused; `last_error` cleared, or a note when blacklisted, locked (with the reason) or archived (status untouched).
- rejected: `error`, `last_error` = "Rejected by Trendyol: reason: detail; …" (500 max).
- pending: nothing; with `docNeeded` a note that a document is needed, status untouched.
- unknown: nothing; the listing may still be going through their batch.
- Only changed columns are written. A later successful stock push clears a note (`last_error = ''`).
- The approval and document notes replace `last_error` only on an `error` row, or over nothing or this read's own texts (`mp_sync_listing_text_is_ours()`): a push refusal on an `active`/`paused` row (no price, a second product under the barcode) stays until a push succeeds.

## Which rows are asked about? (`mp_sync_listing_states()`)
- Last step of each pass, after the push and the order pull (a slow read cannot use their time; deadline checked before each 50-row chunk), per active account whose connector has `listing_status`: rows with a `remote_code`, `remote_id` empty and no queue line `sent` (its verdict, applied by the next poll, would overwrite the read and can stop an approved row for good). The write is conditioned on the code and empty `remote_id` read.
- Rejected rows stay in the set: rejections are fixed in Trendyol's seller panel, and the approval read then sets the row `active` and queues it. An unchanged rejection writes nothing. The screen knows a rejected row by `mp_listing_rejected_prefix()` (from the translated text).
- At most 100 a pass, 50 a call (4 calls), from a random start each pass (`mp_sync_listing_window($ids, mt_rand(), 100)`, wraps round); a clock-based start skipped rows when the job ran every 10 or 15 minutes. Rows Trendyol never answers for cannot hide the others. Approved rows leave the set. A failed call writes the account's error and stops that account for the pass; success does not clear it.
- Matching a product again clears `remote_id` (the content id belongs to the old code), so its new code is read.

## What does the screen show?
- Matched products, for a connector with `listing_status`: "On Trendyol" once `remote_id` is set, "Rejected" with the reason, "Awaiting approval" for an active row without it; a note on a non-error row is shown under the code.

## What is not stored?
- `commission`, `productUrl`, `onSale`: no column (no schema change). Commission shown read-only needs one (plan step 2b-ii, owner question).

## What is unverified?
- No live call; fixtures follow the doc samples. Whether `barcodes` takes a comma list; whether a rejected listing re-enters approval on a new create or needs the update call.

Related: docs/memory/commerce/trendyol-listings.md, docs/memory/commerce/trendyol-stock-price.md
