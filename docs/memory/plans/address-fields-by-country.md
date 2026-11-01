---
name: address-fields-by-country
description: Plan (owner request 2026-11-01): Turkish addresses as İl and İlçe (the city box is the district) in every form, the district box rule, then per-country place lists; steps A1-A8
---

## What is wrong now?
- A Turkish address is asked as "Eyalet / İl" (pick list), "Şehir" and, on shipping only, "İlçe" (#3569, #3641). In Turkey the city is the province, so it is typed twice, while the product reads the city box as the district: `erp_address_from_checkout()` (account card, Paraşüt e-invoice, e-waybill), the n11 and Trendyol imports, the carriers' fallback, and the Turkish default install's own layouts ("İlçe*" or "İlçe/Semt" over the city, "İl" over the state) and contact wording ("İşletme İlçesi/Şehri").
- So İstanbul written under "Şehir" becomes the district on the account card, e-invoice and e-waybill (billing has no district box; the e-waybill ignores a filled `ship_tos.district`); with the box empty, Aras and Kargonomi get the province as the district and Kargonomi refuses a name it cannot find.
- City and district are free text everywhere; the state is a pick list only where `states` has rows (US in both default installs, Turkey in the Turkish one).

## What did the owner decide? (2026-11-01)
- A Turkish address is asked as İl (the state box) and İlçe (the city box) in every form. The separate district box is not drawn for it; it stays for an address abroad whose carrier asks. A Turkish recipient's `ship_tos.district` is written from the city box; readers take `ship_tos.district` when filled, else the city (the e-waybill too). Reason: one rule the product already reads; the box of #3641 is unreleased (2026.11.0), so no shipped site changes.
- The visual designer's templates and the system widgets are checked and brought to the rule (`address-fields-by-country-places.md`). City and district stay free text where no list exists. Recorded in `workflow/decisions-made-product-16.md`.

## How do labels follow the country?
- One pure function gives a country's labels: `District` / `Province` for Turkey ("Province" → "İl" through `tr_add.php`), `City` / `State / Province` elsewhere. The server stamps both texts on the city and state labels; one client script swaps them when the country changes and shows the district box by the new rule (`pg_ship_to_district_script()` grows into it).
- Saved designer trees, template pages and classic custom layouts are not rewritten: the render applies it, as `pg_ship_to_district_tree_fit()` does for the box. Only the default words swap (asterisk, case and spacing ignored); words a designer or a layout changed stay.

## Which questions are decided? (owner 2026-11-01, PR #4683; `workflow/decisions-made-product-17.md`)
1. Source of the Turkish district list: compiled by us from the official public list into the repository, MIT (81 provinces, 973 districts, 51 "Merkez"); no ready-made compilation and its licence.
2. A district not in the list: accepted; the order screen shows a yellow note; a spelling that matches a listed name is corrected ("kadikoy" → "Kadıköy"). No district blocks an order.
3. Other countries: lists as files contributed per country (`includes/geo/places/<CC>.json`); a country without a pack keeps the city as free text. A per-country GeoNames download (A7) is not built.

## What is not in this plan?
- Filling `states` for more countries: once a country has states, `get_valid_zones_for_destination()` and `get_tax_rate_for_address()` match a zone by state, so zones listing only the country stop matching.
- Neighbourhood (mahalle) lists, address autocomplete services, a data step (older rows cannot be told apart).

## Where does it start?
- A1: `commerce/tr-district-plate-code.md`, `tr-district-carriers.md`, `erp/erp-account-country-rules.md`, `designer/system-widget-change-checklist.md`, then `pg_ship_to_district_*` in `includes/fn/shipping.php`. Steps: `address-fields-by-country-steps.md`.
