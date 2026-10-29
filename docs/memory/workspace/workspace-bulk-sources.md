---
name: workspace-bulk-sources
description: A person's own bulk product drafts (bulk_sources.php): several lines under /create product, a channel's spreadsheet, the table reader, ws_bulk_create_card()
---

## Where do the rows come from? (3b, PR #2278; #2982)
- Several lines under `/create product` (`/oluştur ürün`) or its alias `/product` (`/ürün`): `ws_command_product()` sees a list (`ws_bulk_text_is_list()`: more than one non-empty line) and calls `ws_command_product_bulk()`; one line stays the single preview (`workspace-create-commands.md`). The list is laid out before any database call, so more than 200 rows are refused with the `import_products.php` pointer at once. Answer `done` with `bulk` {count, ignored}; the screen toasts `bulk_make_done`.
- A CSV/xlsx/xls/ods file of the channel: the "Make products" button under it (`channel.bulk_products`: may post and make products), action `ws_bulk_create_propose` → `ws_bulk_create_propose()` (file only since #2982). Button and flag go in S4 of `plans/workspace-create-command.md`.
- Out of the channel menu since #2927 (owner, 2026-10-29). The pasted-list window and its keys (`bulk_make_intro`, `_placeholder`, `_go`) went in #2982: a creation window inside the chat is not the right shape (`workflow/decisions-made-workspace-2.md`).
- Table: `ws_bulk_text_table()` (tab, else `;`, else `|`); a file through `ws_bulk_file_table()` (a spreadsheet via `pg_products_import_to_csv()`, a CSV with its first line's separator). `ws_bulk_table_rows()`: a first row naming a known column (`ws_bulk_header_fields()`: import names, English, Turkish) is a header, unknown columns named back; else the first cell is the name and short description, the first amount after it the price. Prices by `pg_products_import_price_cents()`.
- Both go through `ws_bulk_create_card()`: ready, may post, product right, `ws_bulk_create_input()`, then a message of the person's own and the card under it, `request_id 0`. The command's message is the text as typed (`typed` option from `ws_command_run()`, cut at `WS_MESSAGE_MAX`); a file's is "Product drafts: N rows from “file”". Applied, `ws_bulk_step()` sets `ws_change_source()` to the person and a quiet log; decision "Bulk change by {person}: …".

Related: workspace-bulk-create.md (the card, checks, writes), workspace-create-commands.md
