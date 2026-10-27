---
name: workspace-sales-quote-invoice
description: A sales quote turned into an invoice draft from a change card (erp_quote action invoice, PR #1907): no fields, the shared state rule, the writer erp_quote_to_invoice(), the decision, the proposers
---

## What is it?
- Action `invoice`: `id` is the quote, no fields (`ws_change_fields_for()` gives none; `ws_changes_input()` takes none). Card label "Into an invoice draft", headed by the quote's chip.
- Checked at proposal and at apply by `ws_change_erp_quote_invoice_refusal()` (through `ws_change_check()`): the quote as `erp_quote()` reads it (with its invoice's status) against `erp_quote_invoice_refusal()`, the rule `erp_quote_to_invoice()` itself asks: open, expired or accepted pass; invoiced, rejected or cancelled are refused in the quote screen's words. An invoiced quote whose draft was deleted or whose invoice was cancelled reads as accepted and passes.
- Written by `erp_quote_to_invoice($id, applier)` (draft dated today at the prices quoted, `erp.quote.invoiced`, `created_by` the applier); Site Log line through `ws_change_log()`. The writer hands back `invoice_id`; the decision reads "<quote> became the invoice draft <#invoice:id> on …'s proposal."
- Offered in `ws_api_changes_param()`, Claude's guide (claude.php) and Pinegrap AI's `propose_change` (action enum, no field; the quote's id must have been seen).

## What guards it?
- tests/workspace_erp_quote_change_test.php: the catalogue, `erp_quote_invoice_refusal()` per state, the check and writer from source, the three proposers' texts.

Related: docs/memory/workspace/workspace-sales-quote-change.md, docs/memory/erp/erp-quotes.md
