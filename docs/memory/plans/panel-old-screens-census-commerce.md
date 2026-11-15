---
name: panel-old-screens-census-commerce
description: LM1a census of old commerce and ERP panel screens against look A (2026-11-15): how it was checked, what every group changes, the twelve groups in order
---

## What was checked, and how?
- Every root screen calling `pg_page_shell()`/`output_header()` of orders, products, customers (contacts, commissions, affiliates), offers, gift cards, key codes, shipping (countries, states, zones, methods, arrival dates), tax zones, currencies, marketplaces and ERP: 138 screens, read from the code on development (e9ccbec7) with their includes. No sandbox: no screen was loaded.
- Per file it counted lines holding an old pattern: `text-uppercase h5 fg-primary fw-bold` titles, `small`, `fs-*`, `fw-bold`, Bootstrap button colours (`btn-success`, `-primary`, `-danger`, `-outline-*`), `badge`, `style="`, `<style>`, `<h1>`–`<h6>`, `<table>`, `.card` markup; and whether it is on the list table driver, `pg_screen_toolbar()`, a `pg-measure-*` class, the form and status columns.
- Size is an estimate of product lines a step would change: about two lines per flagged line, plus the move to the driver or to the columns where a screen lacks them. Not measured: how a screen looks at a given width, and its JavaScript.
- Payments have no panel screen of their own: gateways are Site Settings (LM1b); refunds sit in View Order and View Return.

## What does every group still change?
- 115 card titles in the old `text-uppercase h5|h6 fg-primary fw-bold` (Bootstrap 6 utilities beat any panel rule, so the markup must change, `ui/look-edit-screens.md`); 17 figure labels `small text-uppercase fg-secondary` (ERP reports, expenses).
- Bootstrap button colours in the save bars and card heads, not `.pg-button` fill, quiet, ghost: the open point of `ui/look-edit-screens.md`. Whether the shared `nav.buttons.navigation` bar draws them once in CSS or each screen changes its markup is the architect's to say before C1.
- Screens with no `pg-measure-edit` that are edit or record screens: `view_order`, `view_return`, `edit_offer`, `edit_erp_quote`, `edit_erp_purchase_order`, `edit_erp_invoice_draft`, `add_erp_manual_invoice`, `add_erp_purchase_order`, `add_erp_quote`, `duplicate_product_group`, `delete_key_codes`.
- `tools/check_look.php` finds few rules of these screens in `backend.src.css` (the counts sit in `.widget`, `#menu`, `#header` and Workspace); their own sizes and colours are in `<style>` blocks inside PHP, which the check skips: Product Builder (2), Marketplace Settings, Marketplace Products, the ERP drawer.

## Which groups, in which order?
- Daily screens first, settings and tools last. C = commerce (`panel-old-screens-census-commerce-shop.md`), E = ERP (`panel-old-screens-census-commerce-erp.md`).
- 1 C1 Orders (~870) · 2 C2 Products (~950) · 3 E1 Invoice, quote and order documents (~850) · 4 C3 Customers, offers, gift cards (~700) · 5 C4a Marketplace Products (~600) · 6 C4b Marketplace Settings (~450) · 7 E2 ERP lists off the driver, part 1 (~550) · 8 E3 ERP reports (~650) · 9 E4 ERP records (~650) · 10 E2b ERP lists off the driver, part 2 (~450) · 11 E5 ERP imports, matching, settings (~850) · 12 C5 Shipping, tax, currency forms and imports (~450).
- C4a and C4b, E2 and E2b may join into one step each if the first comes in well under 1,000.

Screens already on the look and screens outside both censuses: `panel-old-screens-census-commerce-done.md`.
