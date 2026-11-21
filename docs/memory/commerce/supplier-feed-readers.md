---
name: supplier-feed-readers
description: Supplier feed tables (11.0.255) and readers: format from the first bytes, record path, XML/CSV/JSON with a resumable cursor, flat keys, the field proposal (SF1, PR #10771)
module: supplier_feed
---

## Where are the tables?
- `supplier_feeds`, `supplier_feed_items`, `supplier_feed_runs`: step `upgrade_2026_11_0_supplier_feeds()`, label 11.0.255, all three in `get_tables()`. Columns bind: `workflow/decisions-made-schema-38.md`. The screens write `supplier_feeds` (`supplier-feed-screen.md`); the sync writes items and runs (`supplier-feed-sync.md`).

## How is the format told?
- `pg_supplier_feed_format_detect()` on the first bytes after a BOM and white space: `<` is xml, `[` or `{` json, anything else csv. `pg_supplier_feed_format_detect_file()` reads 4 KB.

## How is the record path found?
- `pg_supplier_feed_record_paths($file, $format)` lists every candidate, best first (the screen's Records select); `pg_supplier_feed_record_path()` returns its first.
- XML: element names from the root joined with `/` (`Root/Urunler/Urun`); the shallowest element that holds elements and occurs at least twice, in the first 20,000 elements; with no repeat, the deepest one holding elements. JSON: keys joined with `/` to the list holding the most objects (a shallower one wins a tie), `''` for a top-level list. CSV: `''`.

## What does a read return?
- `pg_supplier_feed_read($file, $format, $record_path, $cursor, $limit)`: `records` (flat arrays of text), `cursor` (to pass back; `null` once the file ended), `error` (`''`, `open`, `parse`, `format`, `too_large`, `no_records`).
- A cut or broken XML file returns `parse` with what it read: a caller treats that as a failed pull, never as the end of the list.

## How are record keys written?
- XML and JSON: levels joined with `/` (`Kategori/Ad`, `brand/name`); an XML attribute `@id`; a repeated element or list item `key`, `key[2]`, `key[3]` (`Resimler/Resim[2]`, `images[2]`). JSON true/false become `1`/`0`, null `''`.
- CSV: the headings, trimmed, BOM removed; an empty heading `Column N`, a repeated one `name[2]`. Blank rows are left out.

## How does each reader resume?
- XML (XMLReader, `LIBXML_NONET`, no entity loading): the cursor is `index`, the records already read; the next tick skips them with `next()` without expanding.
- CSV (`pg_csv_open()`, `pg_csv_read_separator()`): the cursor holds the byte offset, the separator and the headings. A value that is not valid UTF-8 is read as Windows-1254 (Turkish Excel).
- JSON: decoded whole each call, 64 MB at most (`PG_SUPPLIER_FEED_JSON_MAX_BYTES`, else `too_large`); the cursor is the next index.

## How are the fields proposed? (F2)
- `pg_supplier_feed_propose_fields($records)` gives each field (`supplier_key`, `barcode`, `model_code`, `buy_price`, `stock`, `currency`, `vat`, `name`, `brand`, `category`, `description`, `image`) a source key or `''`. Names (Turkish and English: stok kodu, barkod, alış fiyatı, model kodu, sku, ean, cost…) are in `pg_supplier_feed_field_names()`, folded by `pg_supplier_feed_fold()` (Turkish letters to ASCII, letters and digits only).
- A whole match beats a prefix, a prefix beats a contained name; then the shallower key (`title` over `brand/name`), the name listed earlier. One key goes to one field. `[n]` keys are left out.

Related: `commerce/supplier-feed-price-rule.md`.
