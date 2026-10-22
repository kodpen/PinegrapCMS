---
name: erp-manual-invoice-draft-issue
description: Manual ERP invoices: files and endpoints, the draft boundary (_D series, no number), the issue transaction and its refusals, what may change after issue, invoice_manual.php functions, FX drafts.
---

## Which files make up the manual invoice flow?
- Invoice from an order: `add_erp_invoice.php` (an order picker only). Manual: `add_erp_manual_invoice.php`; draft edit: `edit_erp_invoice_draft.php`.
- Shared form and POST reader `includes/erp/invoice_form.php` (`erp_invoice_form_cards()`, `erp_invoice_form_read()`, `erp_invoice_form_prefill()`); line JS `assets/js/erp_invoice_editor.js` (plain `<script src>`, no `.min` twin, not served via `ENVIRONMENT_SUFFIX`).
- Panel JSON endpoints, GET, read-only, gated by `validate_user()` + `validate_erp_access()`: `get_erp_products.php?q=` (`escape_like`, LIMIT 20) and `get_erp_rate.php?currency=&date=` (wrapper of `erp_fx_rate_for()`).

## What is the draft boundary?
- `status = 'draft'` = header + lines: NO number, NO ledger entry, series counter untouched. It sits in the fake series `_D` (`ERP_DRAFT_SERIES`, `issue_year = 0`, `number = id`, `full_number = ''`), because `uniq_number` allows one empty number only (erp-document-numbering).
- A draft is rewritten whole on every save (lines DELETE + INSERT; a draft's lines carry no return link) and is deleted (`erp_invoice_draft_delete()`), never cancelled.

## How is a draft issued?
- `erp_invoice_issue($invoice_id, $created_by)`, one transaction: status check `FOR UPDATE`; active account; the account fields the e-document provider needs (`erp_edoc_invoice_party_missing()`); lines read from the DB and RECALCULATED with `erp_manual_lines_build()` (header totals are not trusted); a second tax refused while a provider is active; period lock; no `_D` series; the rate; credit limit (sales). Only then `erp_next_number()`, so a refusal spends no number.
- Then: header UPDATE (`AND status = 'draft'`), `erp_invoice_snapshot_account()`, line figures rewritten, `erp_account_post()`, `erp_account_refresh_balance()`, `erp_stock_post_invoice()`; after the commit `erp_stock_apply_pending()` and the `erp.invoice.created` event.

## What may change on an issued invoice?
- Figures and lines never: it goes back by return or cancel. `edit_erp_invoice.php` sends a draft to the editor; on an issued invoice the screen edits only `notes` (`erp_action=notes`, POST with token).
- Until the document reaches an e-document provider, two e-document steps also touch it: completing the buyer and carrier (`erp_edoc_invoice_party_fix()`, card and copy together, logged) and moving the date to today (`erp_edoc_invoice_redate()`).

## How is `includes/erp/invoice_manual.php` split?
- `erp_manual_lines_build()` (pure; per line the campaign rate (`offer_discount_rate`), then the typed `discount_rate` on what is left, both through `erp_apply_rate()`; the taxes on the remaining base, per group of one VAT rate and one withholding rate, from `erp_rate_vat()` (#1004): a line's VAT may be a kurus off its own rate x amount, the lines add up to the document's; `grand_total = subtotal − discount_total + tax_total − withholding_total` (`erp-vat-withholding.md`); cap `ERP_MANUAL_MAX_LINES` = 200 lines), `erp_manual_header_build()`, `erp_invoice_draft_save($data, $id)`, `erp_invoice_issue($invoice_id, $created_by)`, `erp_invoice_draft_delete($id)`.
- `erp_invoice_create_manual($data)` keeps its signature and return array: `draft_save + issue` in one transaction (nested counter), for single-call users such as the API.
- A foreign-currency draft may save without a rate (`exchange_rate = 0`, empty source); at issue `erp_fx_rate_for(currency, issue_date)` is tried, else refused. The written rate keeps source `feed` if equal to the stored rate, otherwise `manual`.

Related: docs/memory/erp/erp-manual-invoice-form-and-snapshot.md
