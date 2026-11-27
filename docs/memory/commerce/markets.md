---
name: markets
description: Markets (11.0.286): the markets and market_countries tables, orders.market_id, the rounding codes, the Markets screen and its drawer, what a save and a delete refuse
module: markets
---

## What is stored?
- `markets`: `name` VARCHAR(100), `currency_code` CHAR(3) (a code of the site's `currencies`), `rounding` ENUM `none` / `whole` / `end99`, `enabled`, `sort_order` (the list's order, from 1). No timestamps: the owner's yes covered these columns only (`workflow/decisions-made-schema-54.md`).
- `market_countries`: `market_id`, `country_id` (the `countries` id, as `zones_countries_xref`), unique on `country_id`: a country is in one market at most.
- `orders.market_id` INT, 0 for none: the market that priced the order. Nothing writes it before the cart charges a market (MK6).
- Added by `upgrade_2026_11_0_markets()` (11.0.286); both tables are in `get_tables()`.

## What do the rounding codes mean?
- `none`: to the currency's smallest unit; `whole`: up to whole amounts; `end99`: up to a .99 ending. Labels "To the cent", "Whole amounts", "Ending in .99" (`pg_market_rounding_options()`); an unknown value reads `none` (`pg_market_rounding_code()`). The rounding itself is applied with the price in MK5.

## Where is the screen?
- Markets, `view_markets.php`, gate `validate_ecommerce_access()`: in the Shipping row's panel beside All Currencies, the Ctrl+K chip "Markets", help `commerce-cont#markets`.
- A printed list (List order, Market, Countries, Currency, Rounding, Status, Actions), sorted by its place; the row menu has Move up, Move down, Delete. "Add market" opens the drawer `#market_add`; a row's name or Edit opens `?id=N`, whose drawer `#market_edit` opens with the screen (`assets/js/markets.js`).
- The drawer: Name, Countries (combobox), Currency, Rounding, the On switch; Save, Cancel, and Delete for a market that exists. A refused save is drawn again in its drawer with the typed values.

## What is refused?
- No name, a name over 100 characters, a currency the site does not have, no country (`pg_market_form_values()`).
- A country another market holds: the error names it and its market, nothing is written (`pg_market_country_conflicts()`); it is taken out of the other market first.
- Delete while an order names the market (`pg_market_order_count()`): it is switched off instead.

## What does a site without a market do?
- Exactly what it did: nothing reads the tables before MK5. Plan: `plans/markets-price-lists.md`.
