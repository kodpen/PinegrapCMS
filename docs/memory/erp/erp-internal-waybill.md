---
name: erp-internal-waybill
description: Internal ERP waybill (irsaliye, not e-Irsaliye): files, series, one open waybill per order, derived invoiced state, editor reuse, units and templates.
---

## Which files and tables make up the internal waybill? (phase 4, no schema change)
- `includes/erp/waybills.php` (logic), `includes/erp/waybill_form.php` (form), `includes/erp/templates/waybill_default.html`; screens `erp_waybills.php`, `add_erp_waybill.php`, `edit_erp_waybill.php`, `get_erp_waybill_pdf.php`.
- Tables `erp_waybills` / `erp_waybill_items` exist since phase 0. It is NOT an e-İrsaliye: the Paraşüt API has no endpoint that turns `shipment_documents` into an e-İrsaliye (plan §7.6), so the `edoc_*` columns open only after an endpoint is verified.

## How are series and numbers handled?
- `erp_waybill_series()`: constant `ERP_WAYBILL_SERIES`, else invoice series + `S` (PGF → PGFS). No `config` column was opened (row limit; a new wide config field must be TEXT, see docs/memory/data/config-table-no-varchar.md).
- Number `erp_next_number($series, 'waybill', $year)` at save time, inside the transaction; the document is born `issued`, there is no draft stage.

## What are the order and invoice links?
- One open waybill per order (`erp_waybills_for_order()`); a second entry redirects to the existing one.
- Fill from an order, `erp_waybill_data_from_order()`: `ship_tos` keeps district in `city` and province in `state`; the mapping is the same as `erp_account_for_contact()`, inverted. Carrier/ship date from `erp_order_shipment()`.
- Invoiced state is derived, no column: `erp_waybill_invoice_state()` reads a LEFT JOIN on `erp_invoices` (missing/`cancelled` → `none`, `draft` → `draft`, else `invoiced`). `erp_invoice_draft_delete()` drops the link via `erp_waybill_unlink_invoice()` (guarded by `function_exists`).
- Order-linked waybill → `erp_invoice_for_order()` / `erp_invoice_from_order()`; manual waybill → draft via `erp_invoice_draft_save()`, each line priced and rated by `erp_product_for_line($product, account)` as the editor and `POST /erp/invoices` do: the product's own rate, else the account's zone rate. Before PR #1131 (#1068) a product left to the zone (`tax_rate` NULL) got 0% and the issued invoice carried no VAT.
- An invoiced waybill cannot be cancelled; cancel keeps the number and does not open the series.

## How does the editor and document work?
- Item rows reuse the invoice editor's `data-erp-*` markup and `erp_invoice_editor.js` as-is (no price column). Unit codes `erp_invoice_form_unit_codes()`; the document shows the name, not the code (`erp_waybill_unit_label()`).
- Only the built-in template; an `erp_templates` row with `doc_type = 'waybill'` is created on first request.
- `data-confirm-content` is a native `confirm()` (backend.src.js), not a modal: in automation `window.confirm` is overridden.
