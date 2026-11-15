---
name: look-kv-list
description: Look A's label and value list of a status column (.pg-kv, SH2, #9757): the markup, the second group under a hairline, the grey second line, which screens use it
---

## How is a status column's label and value list drawn?
- `<dl class="pg-kv">` with a `<dt>` and a `<dd>` per row; a `<div>` may group a pair. Block "Look A: label and value list" in `backend.src.css`, from the look sheet's `.kv` (add-page.html).
- Label in the third text (`--pg-fg-3`), value weight 500 at the right edge, both 13 px, row gap 10 px. The label column is as wide as the longest label up to about half the card; a long value wraps in its own column (on a phone too: the architect left it, channel 25 message 5068).

## What about a second group or a second line?
- A second group (Edit Invoice's foreign currency and internet sale rows) is a second list right after the first: `dl.pg-kv.pg-kv-rule`, under a hairline.
- A value's grey second line (tax office, supplier invoice date, carrier tax number, rate source) is `<span class="pg-kv-note">` inside the `dd`.
- A record's amount stays a figure (`.pg-figure-label` / `-value` / `-note`) above the list, as on the Receipt (SH4).

## Which screens use it?
- Edit Invoice's status card, Receipt (`erp_receipt.php`), Marketplace Settings' account card counts. `tests/look_kv_list_test.php` pins the recipe's tokens and these screens.
- Not yet: Edit Invoice's e-document card in the form column, older Summary cards (`pg_design_file_form_summary_card()`, `pg_order_view_row()`).

Related: docs/memory/ui/look-type-and-recipes.md
