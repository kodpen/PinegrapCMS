---
name: workspace-sales-quote-change
description: A sales quote added from a change card (kind erp_quote, PR #1705): fields, the erp_lines value kind, what is filled in and checked, the writer erp_quote_save(), how the card and the assistants show it
---

## What is it?
- `erp_quote` in `ws_change_types()` (includes/workspace/changes.php): a sales quote, create only, ERP right (`erp`, refused to `manage_erp_readonly` through `ws_change_erp_types()`), tag `erp_quote`, table `erp_quotes`. Never a cart quote (plans/workspace-quotes.md, answer 1).
- Proposed like every kind: an application through `workspace.changes.propose` or its task twin, Claude in its answer's `changes`, Pinegrap AI with `propose_change` (field `lines` as one JSON list). The person applies it on the card.
- Only on a site with the ERP and the `erp_quotes` table (`line_data` probe).

## Which fields?
- `account_id` (ids of one `erp_account`), `issue_date`, `valid_until`, `currency` (code 3), `lines` (kind `erp_lines`, [100, 255]), `notes` (long 5000). `ws_change_create_check()` needs one account and lines.
- `erp_lines` (`ws_change_erp_lines()`, pure): a list of objects with the fields of POST /erp/quotes lines (`ws_change_erp_line_fields()`), read as `erp_api_lines_in()` reads them: unit_price whole minor units (no point, comma or exponent), rates 0-100, quantity default 1. A line with a product_id may leave description and tax_rate out; any other needs both. Refusals name `lines[i].field` in the API's words (`ws_change_value_refusal()` returns the validator's sentence).
- Read back from `line_data` by `ws_change_erp_lines_from_built()` (unit_code is unit; the campaign rate is not carried). An `ids` field with a column reads as a list of one.

## What happens when it is proposed?
- `ws_change_erp_quote_fill()` (in `ws_changes_input()`, after the create check): the store's currency when none is named, a product line's name and rate from `erp_product_for_line()` (product rate, else the account's zone rate), fields in catalogue order. The card shows what will be written.
- `ws_change_erp_quote_refusal()` (from `ws_change_check()`, so at proposal and at apply): account active and not `kind = 'supplier'` (the rule of `ws_sales_quote_account_pick()`), currency allowed (`erp_fx_enabled()`), valid_until not before the date, products there and not binned, no second tax where the store names none, then `erp_manual_header_build()` and `erp_manual_lines_build()`, the checks `erp_quote_save()` makes.

## How is it written?
- `ws_change_write_erp_quote()` maps the values with `ws_change_erp_quote_data()` (the shape of `erp_api_quote_in()`: foreign currency takes the rate recorded for the date, else 0) and calls `erp_quote_save()`: numbered in its series, `erp.quote.created` (ERP audit trail, webhooks), `created_by` the person who applied. Site Log line through `ws_change_log()`; no `pg_audit_tables()` line (erp_quotes is not there).
- The decision tags `<#erp_quote:id>`.

## How is it shown?
- Card: one row per line, "2 × Desk · Unit price ₺1.200,00 · VAT %20" (`ws_change_erp_line_text()`, in the quote's currency through `erp_money_out_currency()`). Decision and API `to_shown`: the lines in one text, bare amounts (`ws_change_show()`), the currency row beside.
- Pinegrap AI's search details leave out a list of objects (a quote's lines); `read_record` gives them.

## What is left out on purpose?
- No update, status move, sending or invoicing from a card: the quote screen does them (slice 6 plans an invoicing action). Not a drawer kind, not bulk, not a scheduled action's change or rule record (`ws_scheduled_change_skipped()`, `ws_scheduled_record_types()`).

## What guards it?
- tests/workspace_erp_quote_change_test.php (lines, refusals, text, catalogue, writer and checks from source); the record kind lists (workspace-record-kinds.md); erp_quotes widths in tests/workspace_change_limits_test.php.
