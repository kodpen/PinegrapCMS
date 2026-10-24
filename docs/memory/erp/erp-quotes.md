---
name: erp-quotes
description: ERP sales quotes (4.92) and their signature (8.58): own table, numbering, state, printing, turning into an invoice draft, signature evidence, expiry notice, the Workspace tag.
---

## Why do quotes have a table of their own? (`includes/erp/quotes.php`, `erp_quotes`, 2026.4.4 step 4.92)
- A quote moves no money, stock or tax. It is never written into `erp_invoices`: part of the invoice queries do not look at `doc_type`, so a quote there would leak into balances, VAT and reports.
- It takes its number on first save: `erp_next_number($series, 'proforma', $year)`, series `ERP_QUOTE_SERIES` or the invoice series + `T` (`erp_quote_series()`). Valid `ERP_QUOTE_VALID_DAYS` (30) unless a date is typed.
- Typed in the invoice editor (`erp_invoice_form_cards($liveform, ['quote' => true])`); the lines are kept as JSON on the row. Screens `erp_quotes.php`, `add_erp_quote.php`, `edit_erp_quote.php`, PDF `get_erp_quote_pdf.php`.

## What state is a quote in?
- Stored status: open, accepted, rejected, cancelled, invoiced. Screens and API show `erp_quote_state()`: an open quote past `valid_until` is expired; an invoiced one whose draft was deleted or whose invoice was cancelled reads as accepted again and can be invoiced once more.
- Status changes go through `erp_quote_status_refusal()`, shared by the screen and the API.

## How is it printed and invoiced?
- With the invoice template: `erp_invoice_document_data(0, erp_quote_document_source($quote))` (title QUOTE, the account as it reads today: no snapshot is kept on a quote).
- Into an invoice only through `erp_quote_to_invoice()`: an `erp_invoice_draft_save()` draft dated today at the prices quoted, notes "Quote <number>", event `erp.quote.invoiced`; the draft is issued the usual way.
- The invoice names its quote in `erp_invoices.quote_id` (8.58), written only when the key is given (`array_key_exists`). `erp_quotes.invoice_id`, the other way, is cleared when the quote is opened again.

## How does the signature work? (`includes/erp/signatures.php`, 2026.4.8 step 8.58)
- The customer draws it on the screen or a tablet; it marks the quote accepted (`erp.quote.signed`). One per document in `erp_signatures` (`UNIQUE (doc_type, doc_id)`); the drawing is a `files` row with `erp_doc_type = 'quote_signature'`, served to the ERP right only and embedded in the PDF as a data URI.
- Never deleted, never drawn again. `document_hash` (lines and totals), `image_hash` and an HMAC `seal` show tampering: a quote changed after signing no longer matches, the screen says so and the PDF prints no signature.
- An ordinary electronic signature, not a qualified one.

## When is a quote about to run out announced?
- Hourly, once per quote (`erp_quotes.expiry_notified_at`): erp-alerts.md.

## How is a quote tagged in the Workspace?
- Tag kind `erp_quote`, "Sales quote" / "Satış teklifi" (never a bare "quote": an Offer is "Teklif", a quoted message "Alıntı"); `#satis` or `#salesquote` picks it, Offer keeps `#teklif`. Shown with the ERP right (`manage_erp`), as the quote screens; read over the API and by the assistants with `erp:read`.
- The chip: number, account, total (`ws_money_out()`), state (`erp_quote_state()`); it opens edit_erp_quote.php, which draws the Workspace button. refs.php loads quotes.php alone (`ws_erp_quotes_readable()`), not the bootstrap.
- Its menu's "Copy as a new sales quote" opens add_erp_quote.php `?copy=<id>`, offered to whoever may write ERP documents (not `manage_erp_readonly`). Tag-only: the Workspace writes no quote.
