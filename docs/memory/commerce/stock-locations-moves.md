---
name: stock-locations-moves
description: Stock per location, the operator's side: the Stock locations settings card, the stock history (view_stock_moves.php), the Transfer dialog, the product screen's last moves
module: stock_moves
---

## Where are locations edited?
- Settings › Ecommerce › Stock locations (`pgset-stock-locations`, `includes/settings/commerce.php`): one plain table row per location (name, address, order, default radio, on switch) and a "New location" pair added on save through `pg_stock_location_add()` (the first one seeds the rows, `stock-locations-model.md`).
- `commerce.save.php` runs only when the card's hidden `stock_location_card` was posted and `pg_stock_ready()`: `pg_stock_locations_save_plan()` (pure) checks before anything on the screen is written, marks `stock_location_name[<id>]` / `stock_location_enabled[<id>]` and stops the save; `pg_stock_locations_save()` writes after the card's columns.
- A location is never deleted (its moves name it); it is turned off. The default stays on (`default_disabled`), and the default moves in one statement (`IF(id = ?, 1, 0)`), so there is never none or two. A turned-off location takes no transfer in; its units still count in the total.

## How does the history list moves?
- `view_stock_moves.php` (e-commerce managers: `validate_ecommerce_access()` + `pg_stock_moves_user_may()`) on the list table driver: `pg_stock_move_list_source()` is in `pg_list_table_definitions()` as `view_stock_moves`, sorted by date (default, newest first), product, location or change; search covers SKU, product name, location, reference, note, user and the reason's label.
- Filters in the address: `product` (the SKU, `products.name`, or `#id`, `pg_stock_product_reference()`), `location_id`, `reason`, `from`/`to` (`pg_date_input_iso()`). `pg_stock_move_filters()` drops what is not known; `pg_stock_move_condition()` binds every value, the period from the start of `from` to the end of `to` in the site's zone. An unreadable value stays in its box with `is-invalid` and filters nothing.

## What does a transfer do?
- The Transfer dialog posts to the screen (token checked): `pg_stock_transfer()` checks with `pg_stock_transfer_check()` (whole quantity ≥ 1, both locations known, different, the target on), then two `pg_stock_change()` calls with reason `transfer`, `transaction => false`, inside one `START TRANSACTION`; either refused rolls both back. Both moves share the ref `T<time>-<product>-<from>-<to>` (`pg_stock_transfer_ref()`).
- The total does not change, so nothing is announced (`pg_product_stock_changed()`) and no marketplace is queued. A refused transfer comes back with the dialog open, the fields kept and the field marked.

## What does the product screen show?
- `product_builder.php`'s side column prints `pg_stock_product_moves_card()` in edit mode: the quantity per location, the last 10 moves and a link to the history filtered by the SKU; nothing on a site without locations or for an untracked product.

## What guards it?
- `tests/stock_locations_moves_test.php`: the filters and their condition, the product reference, the transfer check and ref, the save plan (changes only, refusals, forged ids), one label per reason, the list definition and the card's wiring.
