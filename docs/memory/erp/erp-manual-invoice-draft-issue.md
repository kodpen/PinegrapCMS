---
name: erp-manual-invoice-draft-issue
description: Manual ERP invoices: files and endpoints, the draft boundary (_D series, no number), issue transaction, invoice_manual.php functions, foreign-currency drafts.
---

## Which files make up the manual invoice flow?
- Invoice from an order stays in `add_erp_invoice.php` (only an order picker). Manual: `add_erp_manual_invoice.php`; draft edit: `edit_erp_invoice_draft.php`.
- Shared form and POST reader `includes/erp/invoice_form.php` (`erp_invoice_form_cards()`, `erp_invoice_form_read()`, `erp_invoice_form_prefill()`); line JS `assets/js/erp_invoice_editor.js` (plain `<script src>`, no `.min` twin, not served via `ENVIRONMENT_SUFFIX`).
- Panel JSON endpoints, both GET, read-only, gated by `validate_user()` + `validate_erp_access()`: `get_erp_products.php?q=` (`escape_like`, LIMIT 20) and `get_erp_rate.php?currency=&date=` (wrapper of `erp_fx_rate_for()`).

## What is the draft boundary?
- `status = 'draft'` = header + lines: NO number taken, NO ledger entry, series counter untouched.
- `uniq_number(direction, series, number, issue_year)` allows only one empty-number row, so a draft sits in a fake series: `series = '_D'` (`ERP_DRAFT_SERIES`), `issue_year = 0`, `number = id` (UPDATE right after INSERT, same transaction), `full_number = ''`. A real series cannot be named `_D`; `erp_invoice_issue()` rejects it.
- A draft is rewritten whole on every save (lines DELETE + INSERT; a draft's lines cannot carry a return link).
- Drafts are deleted (`erp_invoice_draft_delete()`), not cancelled.

## How is a draft issued?
- `erp_invoice_issue($id, $user)`, one transaction: status check with `FOR UPDATE`; lines read from the DB and RECALCULATED with `erp_manual_lines_build()` (header totals are not trusted); `erp_next_number()`; header UPDATE; `erp_account_post()`; `erp_account_refresh_balance()`.
- An issued invoice stays read-only: `edit_erp_invoice.php` redirects a draft to the editor, and on an issued invoice only `notes` is editable (`erp_action=notes`, POST with token).
- An issued invoice is cancelled as before by `erp_invoice_cancel()` with a counter entry.

## How is `includes/erp/invoice_manual.php` split?
- `erp_manual_lines_build()` (pure; per line `discount_rate` → `discount_amount = erp_apply_rate()`; the taxes on what is left, per group of one VAT rate and one withholding rate, from `erp_rate_vat()` (#1004): a line's VAT may be a kurus off its own rate x amount, the lines add up to the document's; totals share the bridge contract `grand_total = subtotal − discount_total + tax_total`; cap `ERP_MANUAL_MAX_LINES` = 200 lines), `erp_manual_header_build()`, `erp_invoice_draft_save($data, $id)`, `erp_invoice_issue($id, $user)`, `erp_invoice_draft_delete($id)`.
- `erp_invoice_create_manual($data)` keeps its signature and return array: now `draft_save + issue` in one transaction (nested counter); for single-call users such as the API.
- A foreign-currency draft may save without a rate (`exchange_rate = 0`, empty source); at issue, if no rate, `erp_fx_rate_for(currency, issue_date)` is tried, else rejected. The written rate keeps source `feed` if equal to the stored rate, otherwise `manual`.

Related: docs/memory/erp/erp-manual-invoice-form-and-snapshot.md
