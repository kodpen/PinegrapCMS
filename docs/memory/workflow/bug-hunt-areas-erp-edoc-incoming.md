---
name: bug-hunt-areas-erp-edoc-incoming
description: The bug hunt in ERP incoming e-invoices and account sync (k2, 2026-11-26): hostile UBL, a document taken in twice, reads and decisions, automatic links; one fault fixed, what is clean and left
---

## Which area was it?
- Named by the architect (agent channel, 2026-11-26, request 3141): `includes/erp/edoc/ubl.php`, `inbox.php`, `account_sync.php`, the screens `erp_inbox.php`, `erp_inbox_document.php`, `get_erp_inbox_file.php`, `erp_account_sync.php`, `erp_account_sync_item.php`; memory `erp/erp-edoc-incoming-invoices.md`, `erp-edoc-account-sync.md`, `erp-edoc-account-autolink.md`.

## What was fixed?
- ERP › Incoming e-invoices, a document's screen: Link to that invoice (or Mark as taken in) on a page opened before the document was drafted elsewhere put its ETTN on a second purchase invoice, so one supplier invoice stood twice.
- Cause and fix: `erp_edoc_inbox_link()` read no state and held no lock. It now locks the inbox row, reads it again and asks `erp_edoc_inbox_link_refusal()`: a document whose draft or invoice stands is linked to no other invoice ("This document has already been dealt with."). Test: `tests/erp_edoc_inbox_link_test.php`.

## What was read and found clean?
- UBL reader on hostile files (libxml 2.9.14, PHP 8.3): nested and quadratic entity blow-ups are refused by libxml's amplification check ("The document is not a UBL invoice.").
- An external `file://` entity reads nothing (no `LIBXML_NOENT`, `LIBXML_NONET`).
- The zip reader: truncated archives, an end record alone and 65535 entries pointing outside the file all end in "The zip archive is damaged." or "holds no document" (a cut end record also logs two warnings); the inflated size is capped at 32 MB by `gzinflate()`'s limit, whatever the archive declares.
- A document taken in twice by Draft the purchase invoice: `erp_edoc_inbox_import()` reads the state under `FOR UPDATE`; two presses make one draft.
- Reads and decisions: `erp_edoc_inbox_keep()` and `erp_edoc_accounts_keep()` write only provider columns; set aside, link, `no_auto_link` and the account link survive a read. Re-reading the UBL changes the kept document and its type only.
- Automatic links: `erp_edoc_accounts_autolink()` (active cards, shared numbers, one account, no card yet, walk-in excluded), `erp_edoc_account_link_from_invoice()` (no card set aside, linked elsewhere or unlinked by hand), Take in matching an account by number, Send pairing by number.
- Read-only accountant: every POST of the four screens passes `validate_erp_access()`, which refuses it.

## What is left?
- `erp_ubl_read('')` throws a ValueError on PHP 8; nothing reaches it today, as `erp_ubl_unpack()` refuses empty content first.
- A card whose country is not Turkey is linked on its own to an account carrying the same letters and digits, whatever the account's country.
- The document screen runs two product lookups per line on each view; a supplier invoice of tens of thousands of lines is slow to open.
- Align on Account sync applies a chosen direction to the stored card as it stands at the press; a read between page load and press can change the value that travels.
