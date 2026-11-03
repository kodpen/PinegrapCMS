---
name: erp-waybill-save-checks
description: What a delivery note's save checks and fills (named invoice, empty recipient, field-level refusals), the one unit list, the list's three invoice states and the shipment-date term (PR #5708).
---

## Which invoice may a note be tied to?
- `erp_waybill_create()` asks `erp_waybill_invoice_refusal($invoice, $account_id, erp_waybills_for_invoice())` for a named `invoice_id` before a number is taken: a sales invoice (`doc_type` invoice, `direction` sales), of the note's account (an invoice with account 0 is not judged), with no uncancelled note yet. A cancelled or missing invoice is still left unlinked without a refusal.
- Before PR #5708 (#5688) only the form's GET checked this: a changed account tied A's note to B's invoice and wrote B's shipment date; two tabs wrote two notes for one invoice.

## What fills an empty recipient?
- `erp_waybill_ship_to_default()` → pure `erp_waybill_ship_to_fill($data, $account, $default_country)`: an empty title becomes the account's. Street, district, city and state come from the account only when none of the four was typed; the country only when the box is empty or still the form's default (`erp_default_country_code()`, the select is prefilled with it).
- Before (#5689) a typed city/state/country under an empty street was replaced silently.

## How does a refusal mark its box?
- `erp_waybill_header_build()`, `erp_waybill_lines_build()` and `erp_waybill_create()` return `field`: `account_id`, `issue_date`, `ship_date`, `ship_time`, `carrier_vkn`, `driver_tckn`, `lines[n][description|quantity]` (n = 0-based position, as `erp_waybill_form_lines()` redraws with `array_values()`), else `_error`. `add_erp_waybill.php` marks it (#5692). The API still maps messages (`erp_api_waybill_field()`).

## Where do unit names come from?
- `erp_waybill_unit_codes()` in `includes/erp/waybills.php` (loaded by bootstrap) holds the UN/ECE list; `erp_invoice_form_unit_codes()` returns it. Before (#5687) the waybill list fell back to C62 only when `invoice_form.php` was not loaded, so the note screen, drawer and PDF printed KGM, LTR, BX.

## Which states does the list filter?
- `erp_waybills.php` filters the three row states of `erp_waybill_invoice_state()`: `open` (no invoice or cancelled), `drafted` (draft invoice), `invoiced` (issued, `NOT IN ('cancelled','draft')`). Before (#5690) `invoiced` took drafts too.

## Which term names the ship date?
- `Shipment Date` / `Shipment Time` everywhere on the note, the drawer included (it used `Ship Date`, an order term). Turkish: Gönderim Tarihi / Gönderim Saati, and the two error texts say "gönderim" (#5691).

Related: docs/memory/erp/erp-internal-waybill.md, docs/memory/erp/erp-invoice-editor-catalog-offers.md
