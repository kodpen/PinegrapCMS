---
name: erp-vat-exemption-send-check
description: The e-document send refuses a 0% VAT line with no exemption code before any request, for every driver (#11983): where the check sits, how the code is resolved and written, why issuing refuses nothing.
---

## Where is a 0% line without an exemption code stopped?
- In the shared send, `erp_edoc_invoice_send()` (`includes/erp/edoc/service.php`), after the send gate and before the row is claimed (`sending`) or the driver is called: one check for Paraşüt and İşbaşı, none in a driver. A refusal writes nothing, so the row stays `none`/`error` and the invoice is sent again once a code is set.
- `erp_edoc_invoice_lines_exemption($lines)` resolves each line's code with `erp_vat_line_exemption_code()` (the line's own, else its product's `products.vat_exemption_code`, else the store default `config.erp_vat_exemption_code`), then asks `erp_vat_exemption_missing_refusal()` (`includes/erp/rules.php`, pure).
- Before #11983 the send went ahead: Paraşüt created the sales invoice and posted `e_archives` with no `vat_exemption_reason_code`, so GİB's refusal came after a record was opened at the provider.

## What does the refusal say?
- It names each line ("Line 2 (Gift card)", by `line_no`, the text cut at 60 characters) and where a code comes from: the exemption code for zero-rated lines on the Accounting rules card, or the product's own code. The invoice screen, the bulk send of the invoice list and the order card show it as the send's error.
- A line that carries VAT needs no code.

## Why is a code found at send time written onto the line?
- A store default or a product code set after the issue is taken at the send, and written onto a line stored with an empty code (`AND vat_exemption_code = ''`, never over a stored one), as the issue would have written it. Paraşüt's hand-over (`erp_edoc_parasut_submit_invoice()`) reads the lines from `erp_invoice_items` again, so a code only in memory would not reach its `e_archives` request.
- The lines handed to the driver carry the resolved codes (İşbaşı reads `vatExemptionCode` from them).

## Does issuing an invoice refuse such a line?
- No. A store that sends no e-document needs no code; the issue (`invoice_manual.php`, `order_bridge.php`, `returns.php`) writes whatever `erp_vat_line_exemption_code()` finds then, the same resolver the send uses, so the two never disagree on a line's code.

## What is not covered?
- A document already at the provider as a draft (`created`) is not checked again at Hand to GİB: the send that made it is where the check runs.
- GİB's own verdict on a 0% line without a code was never seen live: the staging company's e-document integration fails every e-Archive job (`erp-parasut-open-work` plan).
