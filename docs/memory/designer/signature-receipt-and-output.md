---
name: signature-receipt-and-output
description: Signature evidence receipt, the ^^field^^ token output, the h() ampersand pitfall, and why 'signature' was added to the form_fields.type enum.
---

## What must the evidence receipt show?
- `signature_receipt.php` -> `pg_signature_receipt_html()` shows exactly the set the hash covers, no more: for the "document unchanged" line to mean anything, shown == hashed. `pg_signature_document_parts()` and `pg_signature_document_matches()` do the same read. If you add something to the hash, add it to the receipt.
- If the document changed, the warning goes at the very top: the rest of the receipt is the document's current state, and the reader must know that is not what was signed before reading it.

## How does a `^^field_name^^` token render a signature?
- It prints the signature, not its name. Single point: the `signature` branch of `prepare_form_data_for_output()`; submission view, list view and confirmation screen all pass through it.
- HTML-requesting call gets `<img>`, plain-text call gets the URL; neither gets a control.

## Why do signature links 404 when clicked?
- Pass the query-string separator to `h()` as `&`. An address already written `&amp;` comes out of `h()` as `&amp;amp;`, the browser sends a parameter named `amp;x` and the endpoint finds its value empty.
- Symptom: a link that works when typed but 404s when clicked. Test links by clicking, not by typing in the address bar.

## Why is 'signature' in the `form_fields.type` enum when other controls are not?
- General rule (see form palette): never add to the `form_fields.type` enum, because an unknown type in the palette falls back to a text box, which is acceptable.
- Not acceptable for signature: legacy field screens read the type from this column to decide what to draw, validate and where to write; falling back to a text box would show an empty text box instead of a signature. So `'signature'` was added.
- When adding, extend the enum from the existing definition, never retype it: the column accumulated values over the years and a literal list silently drops values added after that line (`upgrade_2026_4_4_signature_field()` is the example).

Related: docs/memory/designer/signature-field-evidence.md, docs/memory/designer/designer-form-palette-and-fields.md
