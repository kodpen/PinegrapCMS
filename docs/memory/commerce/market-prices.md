---
name: market-prices
description: A product's fixed price in a market (MK9, 11.0.290): the market_prices table, the "Prices by market" card, the cart charging it, the price:<market> columns of the product export and import
module: market_prices
---

## What is stored?
- `market_prices`: `market_id`, `product_id`, `price` INT (minor units of the market's currency), primary key on both, key on `product_id`. No other column: the owner's yes named the table only (`workflow/decisions-made-schema-54.md`).
- Added by `upgrade_2026_11_0_market_prices()` (11.0.290); in `get_tables()`.
- A deleted market (`pg_market_delete()`) and a product deleted for good (`pg_pb_delete_product()`) drop their rows; the Recycle Bin keeps them.

## Where is a fixed price used?
- The cart: `pg_market_cart_price($base_minor, $product_id)` (`includes/fn/market_cart.php`) charges `pg_market_fixed_price()` of the order's market when the product has a row, else the base price converted and rounded by the market's rule. The rule never rounds a fixed price.
- Called from `add_order_item()` and `update_order_item_prices()`; a member's agreed price still wins over both.
- Not yet in the printed catalog prices: `pg_visitor_price()` and `prepare_price_for_output()` take a base amount without a product, so a catalog, search or product page still shows the converted price while the cart charges the fixed one. No plan step covers it yet.
- A marketplace and the product feed are sent the base price; nothing is queued on a fixed price write.

## Where is it set?
- Edit Product, card "Prices by market" (`pg_market_prices_card()`), drawn in the edit mode on a site with a market: one field per market (name, currency code, Off pill), the currency's symbol beside it, placeholder "Converted".
- Only the markets the card drew (`market_price_drawn_<id>`) are written; an empty field takes the fixed price off. A price that is not an amount refuses the save in `pg_pb_validate_product_post()` with "The price for {var:1} is not an amount.".
- A typed price is read by the import's reader (`pg_market_price_cell()`), so "12,50", "1.234,56" and a symbol work; a minus sign is refused.

## How do the export and the import carry it?
- Export: one `price:<market name>` column per market, after the `stock:` columns, the decimal or an empty cell (`pg_market_price_export_headings()`, `_cells()`).
- Import: a `price:` heading is matched to a market by name, case and spaces aside; one naming no market stays in the unknown headings. An empty cell leaves the market alone, 0 takes the price off, a cell that is not an amount leaves the row out under the not-an-amount list.

## Tests?
- `tests/market_prices_test.php`: the write's changes, a typed price, the posted card, the export and import columns, the card's markup, the wiring.
