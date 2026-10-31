---
name: decisions-made-product-12
description: Decisions list, product part 12 (2026-10-31 on): ERP settings cards for role 3, Trendyol address order, old n11 rows data step, phone toolbar, Trendyol Luxe, shipment rules confirmed
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Where does a role-3 user edit the ERP settings cards? (#3828)
- Decided (architect, 2026-10-31, settings recategorization R2b): both places from one code path. The four `erp_settings.php` cards (rules, late fee, e-Fatura threshold, document templates) move into shared functions under `includes/erp/`; `accounting.php` / `accounting.save.php` draw and save them for role < 3 in the pane's one Save; `erp_settings.php` draws the same functions for role 3 with `manage_erp_settings`. No right changes; `settings_pane.php`'s manager gate stays. Refused: a role-3 exception in the dialog gate; a link card instead of the move. The template editor joins the pane only if it fits one Save, else the pane shows each document's state and links to the screen; the screen redirects role < 3 to `#settings/accounting` only once nothing it offers is missing from the pane.

## Trendyol import: which column holds the province? (#3835)
- Decided (architect, 2026-10-31): before 2026.11.0 ships, `mp_trendyol_address()` stores Trendyol's `district` in `city` and its `city` (province) in `state`, as checkout and n11 (#2004) do; `district` unchanged; no data step (only dev sites hold Trendyol rows). Refused: keeping the reversed order for good; a rewrite of dev rows.

## Old n11 orders: are the reversed rows fixed? (#3836)
- Decided (architect, 2026-10-31): a data step in `includes/migrations/2026.11.0.php`, the next free general label when pushed (11.0.137 today), called after `upgrade_2026_11_0_tr_district_plate()`. For `ship_tos` rows of orders mapped to an `n11` account (`marketplace_order_map`, `marketplace_accounts.provider`), with a Turkish country (empty, `TR`, or the Turkish or English name) and `city` naming one of the 81 provinces (`pg_sales_map_tr_regions()`, accent- and case-insensitive as `pg_state_plate_codes()`) while `state` names none: swap the two and fill `district` from the old `state` when empty. A rerun finds nothing; the match is a pure tested function; one `install_note`; no `install_heavy_tables` entry. Refused: leaving them; a read-time guess in the label code.

## Does a screen's toolbar stick on phones? (#3837)
- Decided (architect, 2026-10-31, screen-header-actions H3): no; the bar scrolls with the page as today. The top bar is already fixed on phones, and a second strip (two rows once wrapped) would take a large share of a small screen. If wanted later, only as (c): phones, list screens, a flag in H1's helper.

## Trendyol Luxe listings? (#3838)
- Decided (architect, 2026-10-31): not in 2026.11.0; a later plan item, picked up when a merchant asks and the Luxe API facts (endpoints, channel id, category tree) are gathered. No step now.

## Shipment write path: the two rules? (#3839)
- Confirmed (architect, 2026-10-31): a delivery is recorded only when a save enters or changes the delivery date to today or earlier (`pg_shipment_delivery_entered()`); a future date is an estimate. A parcel's carrier is stored only when named (caller, the method's carrier, the method code); the site default and the number shape stay read-time guesses (`commerce/shipment-write-path.md`, `tests/shipping_test.php`).
