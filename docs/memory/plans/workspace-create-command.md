---
name: workspace-create-command
description: Plan (owner, 2026-10-29): "Make products" leaves the channel menu; creates in a chat go through one /oluştur (/create) command, single preview or bulk card; the pasted-list window and file button go
---

## Why?
- Owner decisions of 2026-10-29 (`workflow/decisions-made-workspace-2.md`): the channel menu holds only the channel's own items, and a creation window inside the chat is not the right shape. Today "Make products" (#2278) sits in the channel menu and opens a pasted-list window; a CSV/xlsx file of the channel carries a button opening it too (`workspace/workspace-bulk-create.md`).

## What is the target?
- `/oluştur ürün <name> <price>` and `/oluştur grup <name>` (`/create product|group`): the single-create preview card (`ws_quick_create_*`, `workspace/workspace-quick-create.md`). Several lines under `/oluştur ürün`: the bulk draft card (3a, up to 200 rows; more → `import_products.php`). `/ürün` (`/product`) stays as an alias of `/oluştur ürün`. Offered only with the product right.

## Which steps? (about 1,000 product lines each, one at a time)
- **S1** "Make products" leaves the channel menu: the `channel.bulk_products && !channel.archived` block of `channelMenu()` in `assets/js/workspace.js` (+ `workspace.min.js` by `php tools/build_js.php`). The file button and the window stay until S3/S4. Test `tests/workspace_channel_menu_test.php`: reads `channelMenu()`'s item keys from workspace.js and lays them against an allowed list whose comment cites the rule, so a new item fails until the list changes. Memory: workspace-bulk-create.md.
- **S2** `/oluştur` / `/create` in `ws_commands()` (`includes/workspace/commands.php`): a kind argument (ürün/product, grup/group, Tab popup), the single preview through `ws_quick_create_input()`/`_save()`; `/ürün`, `/urun`, `/product` an alias of `/oluştur ürün`; a group's name the rest of the line. Files: commands.php, quick_create (group fields), `ws_command_turkish()` names, translations by `tr_add.php`. Tests: workspace_commands_test.php, workspace_quick_create_test.php.
- **S3** Several lines under `/oluştur ürün` → the bulk card through `ws_bulk_text_table()` / `ws_bulk_table_rows()` (`includes/workspace/bulk_sources.php`); the card under the command's message. The pasted-list window (`bulkMakeProducts()` with no message) and its translations go. Tests: workspace_bulk_sources_test.php, workspace_commands_test.php.
- **S4** A channel spreadsheet as the source: `/oluştur ürün` as a reply to, or sent with, a CSV/xlsx/xls/ods file reads it through `ws_bulk_file_table()`. The file's "Make products" button, `bulkMakeProducts()` and `channel.bulk_products` (`includes/workspace/channels.php`) go, with the unused keys. Tests as S3.

## Which steps are done?
- S1 (#2927): the menu item is gone; tests/workspace_channel_menu_test.php guards the menu's item keys.
- S2 (#2967): `/oluştur` (`/create`) ürün|grup with the single card; `/ürün` runs the same function (`workspace/workspace-quick-create.md`).
- S3 (#2982): several lines under `/oluştur ürün` or `/ürün` make the bulk card under the command's message (`ws_command_product_bulk()` → `ws_bulk_create_card()`); the pasted-list window and its keys are gone; `ws_bulk_create_propose()` reads only a file now.

## What did the architect decide? (#2999)
- S4's reply form on someone else's file: anyone who may read the message, post in the channel and change products (as the button was); the drafts are the proposer's own, every row checked again at Apply.
- `/oluştur grup` stays one record; several lines answer with a clear error (rides S4).
- No new kinds now; `/görev` stays its own command (`workflow/decisions-made-workspace-2.md`).

## Where to start?
- S4. Read `workspace/README.md` key rules, workspace-bulk-sources.md, workspace-create-commands.md.
