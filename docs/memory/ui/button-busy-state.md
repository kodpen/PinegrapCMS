---
name: button-busy-state
description: Buttons never change their label or lock for a fixed time while a request runs; the window-level guard against double submits and double clicks (3 s); how an Ajax button disables itself.
---

## May a button say "Saving…" while it works?
- No. A button keeps its label during a request ("Kaydediliyor…", "Oluşturuluyor…" are not written) and is never locked for a fixed time: that kept screens that save over Ajax waiting long after the answer had come back. The old `data-loading-content` attribute was removed; do not bring it back.

## How are double submits stopped?
- `backend.src.js` (`window.pgSubmitGuard`, installed once per window) listens for `submit` and link `click` on `window`, so it runs after every handler on the element and on the document: a second submit of the same form, or a second click on the same link, within 3 seconds is cancelled. Nothing changes on screen, and the submitting button keeps its name and value in the request (a disabled button would drop them). Short on purpose: an answer that is a download leaves the page where it is and must be repeatable at once.
- Left alone: an event a handler already cancelled (a form sent over Ajax, a link handled by script), a target other than `_self`, `download` links, `#` and `javascript:` links, clicks with a modifier key.

## What does a button that sends its own request do?
- It is `disabled` exactly while its request runs, and enabled again on every path: success, an error answer and a network failure.

## Which screens send once for longer than 3 s?
- The barcode stock screens (barcode_increase/decrease_inventory.php): `#barcode_read_button` is disabled while its Read request runs (`complete` enables it); a scan is never held, a repeated scan is a real second unit. A success also fills the visually hidden `#barcode_status` (role=status) with the product and its new quantity. Before (PR #3167) a double click with quantity 5 moved the stock by 6.
- The order screen's card refund dialog (view_order.php): a `window` submit listener lets the first submit of its form through and cancels any later one until the page leaves (reset on `pageshow` when persisted), sets `aria-busy` on the dialog and fills `#payment-refund-status`. The server takes `GET_LOCK(pg_payment_refund_lock_name($order_id), 0)` and turns a second request away; before, a slow provider answer let a second click refund twice.
- Orders > Export for Paraşüt Selected (view_orders.php): Confirm sends the form with `fetch` (`pgParasutOrderExport()` in backend.src.js, to the form's `action` attribute: the hidden `action` input shadows `form.action`), saves the zip under its `Content-Disposition` name, then reloads. A `role=status` overlay shows the wait (`data-wait-text`); an answer that is not a zip removes it and shows a danger toast (`data-error-text`). Before (PR #3348) the page reloaded after a fixed 4 s, which cancelled a slower export after edit_orders.php had already marked its orders exported: no zip, no notice.
- The ERP account CSV import's mapping step (erp_accounts_import.php): the same `window` submit listener on `#erp_import_map_form`; Start import sets `aria-busy` on the form and fills `#erp_import_map_status` with "Importing…". Before, a second press after 3 s ended on "The upload has expired." and hid the counts.
- Import My Site's Import (import_design.php, scan-result page only; Scan streams and is left alone): the same listener on `#import_design_form`, `aria-busy` and "Importing…" in `#import_design_status`. Before, a second press ended on "could not find any items to import" although the pages were written, and scanning again made a second copy.
- ERP Send the e-mail (edit_erp_invoice/quote/purchase_order.php): `mail_form` carries `data-erp-send-once`, with the erp_bank_statement.php listener and status span. The PDF and `email()` (not queued) run in the request; before, a second press after 3 s mailed the document twice.

Related: docs/memory/ui/admin-screen-toolbar-rules.md
