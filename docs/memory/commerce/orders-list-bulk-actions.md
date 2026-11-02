---
name: orders-list-bulk-actions
description: Orders list bulk actions and exports: what the Paraşüt export takes and how it ends, Cancel Selected and open carts, CSV empty cells, the Country filter, the phone bar
module: order_list
---

## Which orders does the Paraşüt export take?
- `edit_orders.php`, `export_orders_for_parasut`: only `complete`/`exported` orders (`pg_order_parasut_exportable()`). Any other selected order is skipped and counted ("… were not completed or were cancelled and skipped"); only written orders get `parasut_exported = 1` and the count. With nothing left no workbook is built.
- Before (#5295), a cancelled order or an open cart went in as a customerless sales invoice with its lines and was marked exported.

## How is the VAT rate checked?
- `pg_order_parasut_tax_rate()`: empty is 0, a number from 0 to 100 (decimal comma accepted), anything else refused with an error before any file is built. The dialog field carries `min="0" max="100" step="0.01"`.

## How does the export end?
- The log line and the liveform notice are written before the zip is streamed; after it only this export's folder (`includes/phpexcel/temps/parasut_<date>/`) is removed and the script `exit`s. Before (#5296) the redirect after the zip added a "headers already sent" warning to the file and the whole `temps/` folder (another export's files too) was deleted.

## Why does Cancel Selected skip incomplete orders?
- An `incomplete` order is a customer's open cart; the row's Cancel button is not drawn for it. The bulk cancel skips it with "… were never completed and skipped", as Mark Shipped Selected does (#5297). `process_order_cancellation()` itself is unchanged.

## What do the CSV exports write for empty values?
- `pg_order_export_card_cell()`: `#` + number, empty when there is none. `pg_order_export_date_cell()`: the recipient's arrival, ship and delivery dates, empty for `0000-00-00` instead of `0/0/0000` (#5303).

## How does the Country filter match "Türkiye"?
- The stored value is the code and `countries.name` is English. `pg_order_country_filter_sql()` adds `OR column IN (codes)` for the countries whose shown name (`pg_geo_country_label()`) contains the text, case and Turkish I folded (`pg_order_country_codes_by_label()`); the code and English-name match stay (#5300).

## How does the bulk bar look on a phone?
- Every button uses Bootstrap Icons and a `title`; the label sits in `.pg-btn-label`. The bar carries `pg-bar-icons`: under 576 px the labels are visually hidden, so the buttons share one or two rows (#5302).

## Which texts changed?
- Row cancel dialog: dismiss "Do Not Cancel", and it says what cannot be undone (e-mail, payment cancel or refund) and that the order can be restored; the bulk confirm says the same (#5298). Turkish "Ship to Name" → "Alıcı Adı", "Awaiting Bank Transfer" → "Havale Bekleyen", the Paraşüt confirm spelled "Paraşüt".
