---
name: site-log-action-lines
description: Which panel actions write a Site Log line on success: order exports, Cloudflare Tools, the Site Log's own Delete buttons
---

## Which lines do the order exports write?
- Both Export Orders versions (`view_orders.php`, multiple files and single file) count the orders whose `complete` → `exported` UPDATE changed a row and the orders `pg_order_card_data_clear()` cleared, and after the loop write `'{var:1} order(s) were exported.'` and `'Card data was removed from {var:1} order(s).'` when the count is above 0. The Audit Trail keeps its one line per order (`core/audit-trail-writers.md`). Before, the Site Log and the dashboard's Activity card saw nothing.

## Which lines does Cloudflare Tools write?
- `cloudflare.php`, only on a successful API answer: `'Cloudflare DNS record added|updated|deleted: {var:1}.'` (the record as type and name, `cf_record_label()`; a delete reads the record with a GET first, the id stands in when that read fails), `'Cloudflare {var:1} updated to {var:2}.'` for the five zone switches (the switch's own label key and the posted value), `'Cloudflare cache purged.'`.

## Do the Site Log's Delete buttons leave a trace?
- Yes: Delete All Site Logs writes `'{var:1} site log(s) were deleted.'` right after `DELETE FROM log`, so the emptied log starts with who emptied it; Delete All Error Logs writes `'{var:1} error_log file(s) deleted.'` when a file went.
- `tests/site_log_writes_test.php` pins each success path.
